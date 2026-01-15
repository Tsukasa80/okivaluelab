# Workflow Checklist (Local -> STG -> Production)

Daily steps
- Edit on Local (VSCode + Codex).
- Check changes with `git status`.
- Save changes with `git add` and `git commit`.
- Deploy files only to STG (theme and mu-plugins).
- Verify on STG (pages, forms, login, logs).
- Deploy files only to Production.
- Verify on Production.

Do not overwrite Production DB
- Do not import Local DB into Production.
- Do not overwrite Production with All-in-One WP Migration.

Deploy targets (Git-managed)
- app/public/wp-content/themes/okivaluelab-child
- app/public/wp-content/mu-plugins
