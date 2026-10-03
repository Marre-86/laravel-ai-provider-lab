const data = JSON.parse(document.getElementById('welcome-data').textContent);

const $ = window.jQuery;
const { marked } = window;

// The raw text each model produced during this page load, so the review can
// quote the answers exactly as they arrived rather than as rendered HTML.
const streamed = {};

function buildSpinner() {
    return $('<div>', { class: 'mt-2 flex items-center gap-2 text-sm text-gray-500', 'data-spinner': '' })
        .append($('<svg>', {
            class: 'size-4 animate-spin',
            viewBox: '0 0 24 24',
            fill: 'none',
            'aria-hidden': 'true',
        }).html(
            '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
            '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 018 4h-4a4 4 0 00-4-4H4z"></path>'
        ))
        .append($('<span>', { text: 'Waiting for the first chunk...' }));
}

function buildQuestionBlock(text) {
    // An empty block reads as a broken pane, so it stays hidden until there is
    // actually a previous question to show.
    return $('<div>', {
        class: 'rounded-lg bg-indigo-50 p-5 ring-1 ring-indigo-200' + (text ? '' : ' hidden'),
    })
        .append($('<h3>', { class: 'text-sm font-semibold text-indigo-900', text: 'Last question' }))
        .append($('<p>', {
            class: 'mt-2 whitespace-pre-wrap text-sm text-indigo-950',
            'data-question': '',
            text: text || '',
        }));
}

function buildResponsePane(model) {
    const exchange = data.exchanges[model] || {};

    // A model with nothing to show contributes no heading and no blocks: a bare
    // label floating in an empty grid reads as a broken pane. The whole pane
    // appears once there is a question or an answer to put in it.
    const isEmpty = !exchange.prompt && !exchange.response;

    return $('<div>', {
        class: 'flex flex-col gap-4' + (isEmpty ? ' hidden' : ''),
        'data-response-pane': model,
    })
        .append($('<div>')
            .append($('<h2>', {
                class: 'text-sm font-semibold text-gray-700',
                text: data.models[model]?.label || model,
            }))
            .append($('<span>', {
                class: 'mt-0.5 block font-mono text-xs text-red-400',
                text: data.models[model]?.provider_label || '',
            })))
        .append(buildQuestionBlock(exchange.prompt))
        .append($('<div>', {
            class: 'rounded-lg bg-gray-50 p-5 ring-1 ring-gray-200' + (exchange.response ? '' : ' hidden'),
            role: 'status',
        })
            .append($('<div>', {
                class: 'mt-2 whitespace-pre-wrap text-sm text-gray-800',
                'data-response': model,
                html: exchange.response ? marked.parse(exchange.response) : '',
            })));
}

function renderExchanges() {
    const $container = $('#exchange-container').empty();
    $('#connection-error').empty();
    $('#prompt').focus();

    Object.keys(data.models).forEach((model) => {
        buildResponsePane(model).appendTo($container);
    });
}

function prepareExchangesForResponses() {
    const prompt = $('#prompt').val();

    // The previous round no longer counts as what these models just answered.
    Object.keys(streamed).forEach((model) => delete streamed[model]);

    Object.keys(data.models).forEach((model) => {
        const $pane = $(`[data-response-pane="${model}"]`);

        const $response = $pane.find(`[data-response="${model}"]`);

        // The previous answer is replaced by the new one.
        $pane.find('[data-question]').text(prompt);

        // An empty pane is hidden entirely, and its blocks are hidden while they
        // are empty, so all three have to come back. parent() is the block itself;
        // closest('div') would stop at the inner text node's own wrapper.
        $pane.removeClass('hidden');
        $pane.find('[data-question]').parent().removeClass('hidden');
        $response.parent().removeClass('hidden');

        $response.empty().append(buildSpinner());
    });
}

function showStreamError($response, message) {
    $response.children('[data-spinner]').remove();

    // Whatever streamed in before the failure stays put; the notice goes below it.
    $response.append(
        $('<p>', {
            class: 'mt-2 text-sm text-red-600',
            'data-stream-error': '',
            text: message,
        })
    );
}

/**
 * Reads a Laravel AI SDK SSE response and reports what arrives.
 *
 * Every streaming endpoint in this app speaks the same protocol, so the frame
 * parsing lives here once and the callers only decide what to do with it.
 */
async function consumeStream(response, { onDelta, onModelError, onEnd, onTruncated }) {
    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}.`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();

    let buffer = '';
    let fullResponse = '';
    let streamFinished = false;

    while (!streamFinished) {
        const { value, done } = await reader.read();

        // The connection closed without a stream_end: the server died mid-stream.
        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });

        const lines = buffer.split('\n');
        buffer = lines.pop();

        for (const line of lines) {
            if (!line.startsWith('data: ')) {
                continue;
            }

            const payload = line.slice(6);

            // Laravel AI SDK sends this after the stream.
            if (payload === '[DONE]') {
                continue;
            }

            const event = JSON.parse(payload);

            if (event.type === 'text_delta') {
                fullResponse += event.delta;
                onDelta(event.delta, fullResponse);
                continue;
            }

            // A failure mid-stream arrives as an error event.
            if ('recoverable' in event) {
                streamFinished = true;
                onModelError(event.message || 'The model returned an error.');
                break;
            }

            if (event.type === 'stream_end') {
                streamFinished = true;
                onEnd(fullResponse);
                break;
            }
        }
    }

    // No stream_end and no error event means the stream was cut off.
    if (!streamFinished) {
        onTruncated(fullResponse);
    }

    return { streamFinished, fullResponse };
}

function truncatedNotice(fullResponse) {
    return 'The stream ended unexpectedly' + (fullResponse ? ', the answer may be incomplete.' : '.');
}

function postStream(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        body: JSON.stringify(body),
    });
}

async function streamModel(model) {
    const $pane = $(`[data-response-pane="${model}"]`);
    const $response = $pane.find(`[data-response="${model}"]`);

    try {
        const response = await postStream(data.streamUrl, {
            prompt: $('#prompt').val(),
            model: model,
        });

        await consumeStream(response, {
            onDelta(delta, full) {
                // The spinner runs only until this model's first chunk.
                $response.children('[data-spinner]').remove();
                $response.append(document.createTextNode(delta));

                // Kept so the review can quote this answer verbatim.
                streamed[model] = full;
            },
            onModelError(message) {
                // A failed answer is not judged, so drop whatever it managed to send.
                delete streamed[model];

                showStreamError($response, message);
            },
            onEnd(full) {
                $response.html(marked.parse(full));
                streamed[model] = full;
            },
            onTruncated(full) {
                $response.html(marked.parse(full));

                delete streamed[model];

                showStreamError($response, truncatedNotice(full));
            },
        });
    } catch (error) {
        // One model failing must not stop the others.
        delete streamed[model];

        showStreamError($response, error.message);
    }
}

/**
 * The answers the review sends.
 *
 * Only answers that finished cleanly are included. A model that errored or
 * was cut off is left out, because half a written answer is not something
 * worth evaluating, and a saved answer from an earlier page load says nothing
 * about the prompt on screen now.
 */
function collectedResponses() {
    return Object.fromEntries(
        Object.keys(data.models).map((model) => [model, streamed[model] ?? ''])
    );
}

async function reviewResponses() {
    const $output = $('#review-output');

    // The output area only appears once the button is pressed.
    $output.removeClass('hidden');

    const responses = collectedResponses();

    if (!Object.values(responses).some((response) => response.trim())) {
        showStreamError($output, 'There are no model responses to evaluate yet.');

        return;
    }

    $output.empty().append(buildSpinner());

    $('#review-button').prop('disabled', true);
    $('#review-spinner').removeClass('hidden');
    $('#review-label').text('Evaluating...');
    document.getElementById('prompt-panel')?.classList.add('hidden');

    try {
        const response = await postStream(data.reviewUrl, {
            prompt: $('#prompt').val(),
            instruction: $('#review-instruction').val(),
            model: $('#review-model').val(),
            responses: responses,
        });

        await consumeStream(response, {
            onDelta(delta) {
                $output.children('[data-spinner]').remove();
                $output.append(document.createTextNode(delta));
            },
            onModelError(message) {
                showStreamError($output, message);
            },
            onEnd(full) {
                $output.html(marked.parse(full));
            },
            onTruncated(full) {
                $output.html(marked.parse(full));
                showStreamError($output, truncatedNotice(full));
            },
        });
    } catch (error) {
        showStreamError($output, error.message);
    } finally {
        $('#review-button').prop('disabled', false);
        $('#review-spinner').addClass('hidden');
        $('#review-label').text('Evaluate responses');
        document.getElementById('prompt-panel')?.classList.remove('hidden');
    }
}

$(document).ready(function () {
    $('#prompt').focus();
    renderExchanges();
});

$('#submit-button').on('click', async function () {
    prepareExchangesForResponses();
    // Hide evaluation block on each new request until responses complete.
    $('#review-panel').addClass('hidden');
    $('#review-output').addClass('hidden').empty();
    $('#submit-button').prop('disabled', true);
    $('#submit-spinner').removeClass('hidden');
    $('#submit-label').text('Generating...');

    // Every model streams independently and concurrently.
    await Promise.allSettled(
        Object.keys(data.models).map((model) => streamModel(model))
    );

    // Only now is there something worth evaluating.
    $('#review-panel').removeClass('hidden');

    $('#submit-button').prop('disabled', false);
    $('#submit-spinner').addClass('hidden');
    $('#submit-label').text('Ask LLM');
});

$('#review-button').on('click', reviewResponses);

$('#review-instruction').on('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        $('#review-button').trigger('click');
    }
});

$('#prompt').on('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        $('#submit-button').trigger('click');
    }
});
