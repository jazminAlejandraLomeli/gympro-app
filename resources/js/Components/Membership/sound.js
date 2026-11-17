/** Sonidos suaves con WebAudio (sin archivos). */
let ctx = null;

export function unlockAudio() {
    try {
        ctx ??= new (window.AudioContext || window.webkitAudioContext)();
        if (ctx.state === 'suspended') ctx.resume();
    } catch {
        ctx = null;
    }
}

const NOTES = {
    granted: [
        [660, 0],
        [880, 0.13],
    ],
    denied: [
        [262, 0],
        [196, 0.2],
    ],
    invalid: [[330, 0]],
};

export function playTone(kind) {
    unlockAudio();
    if (!ctx) return;

    for (const [freq, offset] of NOTES[kind] ?? NOTES.invalid) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        const start = ctx.currentTime + offset;

        osc.type = 'sine';
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.2, start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.25);
        osc.connect(gain).connect(ctx.destination);
        osc.start(start);
        osc.stop(start + 0.28);
    }
}
