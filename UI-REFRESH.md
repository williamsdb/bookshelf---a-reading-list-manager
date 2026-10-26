# UI refresh

The refresh adds warm neutral surfaces, green accents, serif headings, and updated
cards, navigation, forms, and tables. It also includes dark-theme overrides and
mobile spacing. Application routes, data, and reading actions are unchanged.

## Instant rollback

Select **Classic appearance** in the footer on any page. This immediately disables
the refresh. The preference is saved in this browser; uncheck it to try the refresh
again. Light/dark mode remains a separate setting. If browser storage is blocked,
the switch still works for the current page.

## Code rollback

Work is isolated on `codex/ui-refresh`, based on the original `main` branch.
Changes are initially uncommitted, so switching branches alone will carry them
along. To keep the work before returning to main, commit these five files on the
refresh branch first:

```
git add UI-REFRESH.md src/templates/header.tpl src/templates/footer.tpl src/assets/css/refresh.css src/assets/js/appearance.js
git commit -m "Refresh Bookshelf appearance with a classic UI switch"
git switch main
```

Alternatively, remove the two new asset references from `header.tpl` and the
appearance-control label from `footer.tpl` to disable the refresh for everyone.
The original `custom.css` is unchanged. No database rollback is needed.

## Verification

Rendered the home template with the installed Smarty engine and representative
sample books. Visually checked the rendered page and verified Classic restores
the original appearance and persists after a reload. This preview uses sample
data; live database workflows were not exercised. The installed Smarty version
emits deprecation notices under local PHP 8.5.
