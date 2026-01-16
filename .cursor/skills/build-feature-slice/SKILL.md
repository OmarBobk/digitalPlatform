---
name: build-feature-slice
description: Build an end-to-end vertical slice (model → UI → validation → persistence → UX states) with minimal scope and safe diffs.
---

# Build Feature Slice

## When to use
- You want the fastest path to shipping a working feature (not a perfect system).

## Instructions
1. Restate the user goal in 1 sentence.
2. Identify the minimum “happy path” flow (start → action → result).
3. List required files to touch (keep it small).
4. Implement in this order:
   - DB/model (if needed)
   - service/action (business logic)
   - Livewire component (thin)
   - Blade UI (Flux/Tailwind)
   - UX states (loading, error, empty)
5. Add basic validation and meaningful error handling.
6. Provide a short manual test checklist.

## Output format
- “Plan” (bullets)
- “Changes” (file list)
- “Code” (snippets)
- “Test checklist”
