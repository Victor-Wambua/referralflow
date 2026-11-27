# Accessibility

Target WCAG 2.2 AA (a design target; no conformance claim: needs audit with assistive technology users).
Verified by script (`tools/contrast.mjs`): 44 token pairs (light/dark) 0 failures; 3,018 rendered text elements across 20 routes (3 demo accounts) x light/dark x desktop/mobile, 0 below 4.5:1 (3:1 large). Non-text: input borders #7D766A on white and focus ring meet 3:1 in checks.
Design measures: skip link; landmarks (nav, main); visible 3px focus ring; 48px min targets; status = text + dot (not colour alone); labels bound to inputs; errors in role=alert text beneath fields, aria-invalid, focus to first error; live region announces toasts; stepper uses aria-current=step; tracker is an ordered list; chart has aria-label summary and the same data in labelled bars/table; dialogs use native `<dialog>` (focus trap, Esc); reduced-motion respected; text resizes (rem-based) and reflows at 320px; no timing traps except the privacy timeout which warns 60 s and can be extended.
Not done: screen reader walkthroughs (VoiceOver/TalkBack) and keyboard audit on real devices; Swahili copy; plain-language testing with clinicians.
