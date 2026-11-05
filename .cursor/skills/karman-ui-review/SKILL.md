---
name: karman-ui-review
description: Verify a rendered Karman Telekom interface after a UI change or during a UX audit. Check the real task, responsive Arabic RTL layout, rendered type, effective contrast, keyboard, and relevant states.
---

# Review the running flow

1. Open the affected route in the local or preview app with suitable test data and the relevant role. Check the requested widths. Use 1440px and 1024px as desktop and tablet review widths when applicable, plus 390px when mobile is in scope.
2. Try the main task and one important boundary case, such as an empty result, duplicate assignment, or validation failure. Check that the user can locate the action, tell what branch or record it affects, and understand the outcome. Inspect keyboard focus, controls, dialogs, and return of focus.
3. Inspect actual rendered Arabic text, long content, overflow, and mixed RTL and LTR strings. Measure effective contrast on the rendered color pairs against WCAG 2.2 AA. Check text, indicators, and control boundaries where the standard applies. Review touch targets at the relevant widths.
4. Return the tested route, widths, role, and actions, along with concise findings ordered by impact. Attach one useful screenshot per requested width when available. Distinguish browser observations, code inspection, and checks blocked by environment or data.
