# Localization

Metribay's app (Core, Cloud and Desktop) and the metribay.com site support twelve languages: en, bg, cs, de, es, fr, it, nl, pl, sk, sl, sr. English is the source language and the fallback. This page says where everything lives, so that adding or changing a string, or a language, is quick.

## Where the files are

| What | File |
| --- | --- |
| Supported app languages (code → native name) | `core/config/metribay.php`, key `locales` |
| Core interface strings | `core/lang/{code}.json` (one flat JSON file per language; English has no file) |
| Framework messages (validation, auth, passwords, pagination) | `core/lang/{code}/*.php` |
| Cloud-only strings (billing, team, workspaces, mail, auth pages) | `cloud/lang/{code}.json` |
| Language resolution | `core/src/Services/Locales.php` |
| Per-request locale + `?lang=` | `core/src/Http/Middleware/SetLocale.php` (pushed onto the `web` group by `MetribayServiceProvider`) |
| Language switcher (POST `/locale`) | `core/src/Http/Controllers/LocaleController.php`, view `core/resources/views/partials/language-select.blade.php` (used on Profile and the sign-in pages) |
| Saved preference | `users.locale`; `Core\Models\User::preferredLocale()` sends mail and queued notifications in it |
| Cloud sign-up language | `cloud/app/Jobs/RegisterAccount.php`, `cloud/app/Http/Controllers/ConfirmRegistrationController.php` |
| Site copy (marketing pages) | `site/src/i18n/copy/{code}.ts`, assembled by `site/src/i18n/copy.ts`, typed by `copy-types.ts` |
| Site locales and legal text | `site/src/i18n/locales.ts`, `site/src/i18n/legal.ts`, `site/src/i18n/legal/` |
| Site user docs | `site/src/content/docs/docs/` (English) and `site/src/content/docs/{code}/docs/`; sidebar in `site/src/i18n/docs-sidebar.ts` |
| Site → app language hand-off | `site/src/lib/urls.ts` (non-English pages link to the app with `?lang={code}`) |

Core loads its JSON with `loadJsonTranslationsFrom(core/lang)`. A host application's own `lang/{code}.json` (for example Cloud's) is merged on top, so the host can add strings and override Core's wording.

## How the app picks a language

`Locales::forRequest()` decides, in this order:

1. The signed-in user's saved `users.locale`.
2. The session value `metribay_locale` (`Locales::SESSION_KEY`). A guest sets it with the language switcher or by arriving with `?lang={code}`. A signed-in user's `?lang=` is ignored.
3. The browser's `Accept-Language`, matched against the supported codes.
4. `config('app.locale')`, otherwise the first configured locale.

The switcher saves the choice to the session and, when signed in, to `users.locale`. Unknown codes are ignored, so English is shown.

## Adding or changing a string

- Write the English text directly in `__('…')` or `trans_choice('…')`. The English text is the key, so changing English wording means renaming the key in every language file.
- Add the same key to all eleven non-English JSON files: `core/lang/` for Core strings, `cloud/lang/` for Cloud-only ones. The files are sorted by key and indented with four spaces. Keep that order when adding keys (a sorted `json.dumps(..., ensure_ascii=False, indent=4)` keeps it), so diffs only show the new lines.
- Placeholders such as `:chart` or `:count` stay untranslated. Metric labels from `MetricRegistry` are translation keys too; translate them with `__()` before inserting them into a sentence.
- Plurals use Laravel ranges. English uses `{1} … |[2,*] …`. Czech, Slovak, Polish and Serbian need `{1}|[2,4]|[5,*]`; Slovenian `{1}|[2]|[3,4]|[5,*]`.
- No test checks that every key exists in every language. A missing key quietly falls back to English, so check by hand (for example with `__('…')` under each locale, as in the release feature checks).
- German uses "du". Other languages follow the existing wording in their file.

## Adding a language

1. Add the code and its native name to `locales` in `core/config/metribay.php`.
2. Create `core/lang/{code}.json`, `core/lang/{code}/` (copy the framework PHP files and translate them) and `cloud/lang/{code}.json`.
3. Add the site locale (`site/src/i18n/locales.ts`, `copy/{code}.ts`, legal text and docs pages; see `site/AGENTS.md`).
4. Extend `core/tests/Feature/LocalizationTest.php` where it lists languages.

## Site docs and app labels

Site docs quote the app's labels in bold exactly as they appear in `core/lang/{code}.json` and `cloud/lang/{code}.json`, without inflecting them. Where grammar needs a different form, rephrase around the label (for example "the **Ustawienia projektu** page"). Labels the app hasn't translated stay English.
