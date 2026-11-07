/**
 * Karman TRY amount entry: Latin digits, period groups of three, decimal comma ("10.000,50").
 * Mirrors App\Services\Purchasing\LocalizedMoney, which stays authoritative on the server.
 * Text that does not fit the grammar is left exactly as typed and flagged, never rewritten.
 */
const GRAMMAR = /^(\d+|[1-9]\d{0,2}(?:\.\d{3})+)(?:,(\d{0,2}))?$/;

export function normalizeMoneyText(text) {
    return String(text ?? '')
        .replace(/[\u0660-\u0669]/g, (digit) => String(digit.charCodeAt(0) - 0x0660))
        .replace(/[\u06F0-\u06F9]/g, (digit) => String(digit.charCodeAt(0) - 0x06f0))
        .replace(/\u066B/g, ',')
        .replace(/\u066C/g, '.')
        .replace(/[\u200E\u200F\u061C]/g, '');
}

function groupThousands(digits) {
    return digits.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** Regroups an edit of a valid value, where every period is display grouping. */
function formatEdited(value) {
    if (!/^[\d.,]*$/.test(value)) {
        return null;
    }

    const parts = value.split(',');
    const fraction = parts[1];

    if (parts.length > 2 || (fraction !== undefined && !/^\d{0,2}$/.test(fraction))) {
        return null;
    }

    const integer = parts[0].replace(/\./g, '');

    if (integer === '' && fraction === undefined) {
        return '';
    }

    return groupThousands(integer === '' ? '0' : integer) + (fraction === undefined ? '' : `,${fraction}`);
}

/** Formats text whose periods must already be valid groups, such as a paste; only surrounding whitespace is dropped. */
function formatStrict(value) {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    return GRAMMAR.test(trimmed) ? formatEdited(trimmed) : null;
}

export function moneyToMinor(text) {
    const match = GRAMMAR.exec(normalizeMoneyText(text).trim());

    if (!match) {
        return null;
    }

    const whole = match[1].replace(/\./g, '').replace(/^0+(?=\d)/, '');

    if (whole.length > 13) {
        return null;
    }

    return Number(whole) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

export function formatMoney(minor) {
    const sign = minor < 0 ? '-' : '';
    const absolute = Math.abs(minor);

    return `${sign}${groupThousands(String(Math.floor(absolute / 100)))},${String(absolute % 100).padStart(2, '0')}`;
}

function significantCount(text) {
    return (text.match(/[\d,]/g) ?? []).length;
}

function positionAfter(formatted, count) {
    let seen = 0;

    for (let index = 0; index < formatted.length && count > 0; index++) {
        if (/[\d,]/.test(formatted[index]) && ++seen === count) {
            return index + 1;
        }
    }

    return count > 0 ? formatted.length : 0;
}

export function moneyInput({ model }) {
    return {
        invalid: false,

        init() {
            this.show(this.$wire.$get(model));
            this.$wire.$watch(model, (value) => {
                if (String(value ?? '') !== this.$refs.input.value) {
                    this.show(value);
                }
            });
        },

        /** Server-provided values: reset, prefill, or resume. */
        show(value) {
            const text = String(value ?? '');
            const formatted = formatStrict(normalizeMoneyText(text));

            this.$refs.input.value = formatted ?? text;
            this.invalid = formatted === null;

            if (this.$refs.input.value !== text) {
                this.$wire.$set(model, this.$refs.input.value, false);
            }
        },

        /** Backspace or Delete next to a group separator removes the neighbouring digit instead. */
        beforeInput(event) {
            const input = event.target;
            const start = input.selectionStart;

            if (this.invalid || start !== input.selectionEnd) {
                return;
            }

            const backward = event.inputType === 'deleteContentBackward' && input.value[start - 1] === '.';
            const forward = event.inputType === 'deleteContentForward' && input.value[start] === '.';

            if (!backward && !forward) {
                return;
            }

            const digit = backward ? start - 2 : start + 1;

            if (digit < 0 || digit >= input.value.length) {
                return;
            }

            event.preventDefault();
            this.apply(input.value.slice(0, digit) + input.value.slice(digit + 1), backward ? digit : start, true);
        },

        onInput(event) {
            const typedPeriod = normalizeMoneyText(event.data ?? '').includes('.');
            const lenient = !this.invalid && !typedPeriod && !['insertFromPaste', 'insertFromDrop'].includes(event.inputType);

            this.apply(event.target.value, event.target.selectionStart, lenient);
        },

        apply(raw, caret, lenient) {
            const input = this.$refs.input;
            const normalized = normalizeMoneyText(raw);
            const formatted = lenient ? formatEdited(normalized) : formatStrict(normalized);

            this.invalid = formatted === null;

            if (formatted === null) {
                if (input.value !== raw) {
                    input.value = raw;
                    input.setSelectionRange(caret, caret);
                }
            } else {
                const count = significantCount(normalizeMoneyText(raw.slice(0, caret))) + (normalized.startsWith(',') ? 1 : 0);
                const position = positionAfter(formatted, count);

                input.value = formatted;
                input.setSelectionRange(position, position);
            }

            this.$wire.$set(model, input.value, false);
        },
    };
}

export function registerMoneyInput(Alpine) {
    Alpine.data('moneyInput', moneyInput);
    Alpine.magic('tryMoney', () => ({ toMinor: moneyToMinor, format: formatMoney }));
}
