const data = JSON.parse(document.getElementById('welcome-data').textContent);

const $ = window.jQuery;
const { marked } = window;

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
    $response.removeClass('text-gray-800').addClass('text-red-600').text(message);
}

async function streamProvider(provider) {
    const $pane = $(`[data-response-pane="${provider}"]`);
    const $response = $pane.find(`[data-response="${provider}"]`);

    try {
        const response = await fetch(data.streamUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
            body: JSON.stringify({
                prompt: $('#prompt').val(),
                provider: provider,
            }),
        });

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

                    // The spinner runs only until this provider's first chunk.
                    $response.children('[data-spinner]').remove();

                    $response.append(
                        document.createTextNode(event.delta)
                    );
                }

                // A provider failure mid-stream arrives as an error event.
                if ('recoverable' in event) {
                    streamFinished = true;

                    showStreamError($response, event.message || 'The provider returned an error.');

                    break;
                }

                if (event.type === 'stream_end') {
                    streamFinished = true;

                    $response.html(marked.parse(fullResponse));

                    break;
                }
            }
        }

        // No stream_end and no error event means the stream was cut off.
        if (! streamFinished) {
            $response.html(marked.parse(fullResponse));

            showStreamError(
                $response,
                'The stream ended unexpectedly' + (fullResponse ? ', the answer may be incomplete.' : '.'),
            );
        }
    } catch (error) {
        // One provider failing must not stop the others.
        showStreamError($response, error.message);
    }
}

$(document).ready(function () {
    $('#prompt').focus();
    renderExchanges();
});

$('#submit-button').on('click', async function () {
    prepareExchangesForResponses();
    $('#submit-button').prop('disabled', true);
    $('#submit-spinner').removeClass('hidden');
    $('#submit-label').text('Generating...');

    // Every provider streams independently and concurrently.
    await Promise.allSettled(
        Object.keys(data.providers).map((provider) => streamProvider(provider))
    );

    $('#submit-button').prop('disabled', false);
    $('#submit-spinner').addClass('hidden');
    $('#submit-label').text('Ask LLM');
});

$('#prompt').on('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        $('#submit-button').trigger('click');
    }
});
