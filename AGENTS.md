# Company rules

Before changing ordering, payment, cancellation, inventory, or reporting behavior, check the relevant company documents in `C:/Users/User/Desktop/Company informations` and `docs/company-rules.md`.

If a requested change conflicts with a company rule or document, tell the user about the specific conflict before implementing it. Distinguish documented requirements, the user's explicit clarifications, and implementation choices. Do not silently remove a business rule or infer a replacement policy.

Preserve unrelated working-tree changes. Use the guarded disposable database environments for tests and browser fixtures; never run destructive verification against business data.
