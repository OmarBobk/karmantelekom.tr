---
name: karman-ui-design
description: Design or implement Karman Telekom interfaces with deliberate typography, semantic colors, hierarchy, spacing, and responsive Arabic RTL behavior. Use for new screens, visual redesigns, and scoped UI improvements.
---

# Design the interface

1. Start with the primary task and any relevant benchmark handoff. Arrange the screen in the order the user needs to read and act. Name the first visual focus, primary action, and secondary information. Place actions near the records they affect and choose a density appropriate for daily use.
2. Choose the type system by testing Arabic labels, long names, mixed-script device names, codes, and numeric values. Specify the family, weights, sizes, and line heights for the text roles in this screen. Check legibility and scanning at the intended density. Use a project font when it serves the task, and propose an alternative when a concrete problem warrants it.
3. Derive the palette from the project identity and assign clear roles to surface, text, action, selection, focus, and status. Use contrast and hierarchy to decide prominence. Give status a text label. Choose a spacing rhythm and control sizes that fit the target input devices. Reuse suitable existing tokens and components.
4. Compose each requested viewport for the same task. Use Arabic copy, RTL and logical spacing, and isolate values such as KT01, IMEIs, phone numbers, and amounts. Design the relevant loading, empty, filtered-empty, error, and success states and keep the action recoverable.
5. In a proposal, describe one direction in up to eight bullets. Include the layout, type choices, color roles, tablet behavior, and the UX reason behind the main choice. In implementation, use the existing application stack, documented routes and permissions, and real actions. Identify shared design-system changes separately from page-local choices.
