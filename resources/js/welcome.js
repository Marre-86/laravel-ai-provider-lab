const data = JSON.parse(document.getElementById('welcome-data').textContent);

const $ = window.jQuery;
const { marked } = window;

// The raw text each provider produced during this page load, so the review can
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
    return $('<div>', { class: 'rounded-lg bg-indigo-50 p-5 ring-1 ring-indigo-200' })
        .append($('<h3>', { class: 'text-sm font-semibold text-indigo-900', text: 'Last question' }))
        .append($('<p>', {
            class: 'mt-2 whitespace-pre-wrap text-sm text-indigo-950',
            'data-question': '',
            text: text || '',
        }));
}

function buildResponsePane(provider) {
    const exchange = data.exchanges[provider] || {};

    return $('<div>', {
        class: 'flex flex-col gap-4',
        'data-response-pane': provider,
    })
        .append($('<div>')
            .append($('<h2>', {
                class: 'text-sm font-semibold text-gray-700',
                text: data.providers[provider]?.label || provider,
            }))
            .append($('<span>', {
                class: 'mt-0.5 block font-mono text-xs text-red-400',
                text: data.providers[provider]?.model || '',
            })))
        .append(buildQuestionBlock(exchange.prompt))
        .append($('<div>', { class: 'rounded-lg bg-gray-50 p-5 ring-1 ring-gray-200', role: 'status' })
            .append($('<div>', {
                class: 'mt-2 whitespace-pre-wrap text-sm text-gray-800',
                'data-response': provider,
                html: exchange.response ? marked.parse(exchange.response) : '',
            })));
}

function renderExchanges() {
    const $container = $('#exchange-container').empty();
    $('#connection-error').empty();
    $('#prompt').focus();

    Object.keys(data.providers).forEach((provider) => {
        buildResponsePane(provider).appendTo($container);
    });
}

function prepareExchangesForResponses() {
    const prompt = $('#prompt').val();

    // The previous round no longer counts as what these providers just answered.
    Object.keys(streamed).forEach((provider) => delete streamed[provider]);

    Object.keys(data.providers).forEach((provider) => {
        const $pane = $(`[data-response-pane="${provider}"]`);

        // The previous answer is replaced by the new one.
        $pane.find('[data-question]').text(prompt);

        const $response = $pane.find(`[data-response="${provider}"]`);
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
async function consumeStream(response, { onDelta, onProviderError, onEnd, onTruncated }) {
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

            // A provider failure mid-stream arrives as an error event.
            if ('recoverable' in event) {
                streamFinished = true;
                onProviderError(event.message || 'The provider returned an error.');
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

async function streamProvider(provider) {
    const $pane = $(`[data-response-pane="${provider}"]`);
    const $response = $pane.find(`[data-response="${provider}"]`);

    try {
        const response = await postStream(data.streamUrl, {
            prompt: $('#prompt').val(),
            provider: provider,
        });

        await consumeStream(response, {
            onDelta(delta, full) {
                // The spinner runs only until this provider's first chunk.
                $response.children('[data-spinner]').remove();
                $response.append(document.createTextNode(delta));

                // Kept so the review can quote this answer verbatim.
                streamed[provider] = full;
            },
            onProviderError(message) {
                // A failed answer is not judged, so drop whatever it managed to send.
                delete streamed[provider];

                showStreamError($response, message);
            },
            onEnd(full) {
                $response.html(marked.parse(full));
                streamed[provider] = full;
            },
            onTruncated(full) {
                $response.html(marked.parse(full));

                delete streamed[provider];

                showStreamError($response, truncatedNotice(full));
            },
        });
    } catch (error) {
        // One provider failing must not stop the others.
        delete streamed[provider];

        showStreamError($response, error.message);
    }
}

/**
 * The answers the review sends.
 *
 * Only answers that finished cleanly are included. A provider that errored or
 * was cut off is left out, because half a written answer is not something
 * worth evaluating, and a saved answer from an earlier page load says nothing
 * about the prompt on screen now.
 */
function collectedResponses() {
    return Object.fromEntries(
        Object.keys(data.providers).map((provider) => [provider, streamed[provider] ?? ''])
    );
}

async function reviewResponses() {
    const $output = $('#review-output');

    // The output area only appears once the button is pressed.
    $output.removeClass('hidden');

    const responses = collectedResponses();

    if (!Object.values(responses).some((response) => response.trim())) {
        showStreamError($output, 'There are no provider responses to evaluate yet.');

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
            provider: $('#review-provider').val(),
            responses: responses,
        });

        await consumeStream(response, {
            onDelta(delta) {
                $output.children('[data-spinner]').remove();
                $output.append(document.createTextNode(delta));
            },
            onProviderError(message) {
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

    // Every provider streams independently and concurrently.
    await Promise.allSettled(
        Object.keys(data.providers).map((provider) => streamProvider(provider))
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
