# Diversworld - dicoma
## Dive Course Manager
### Managing of Dive Courses für Clubs and Shops 

#### Features
- Dive Course Management
- Dive Course Booking
- Dive Course Booking for Clubs
- Dive Course Booking for Shops
- Dive Course Booking for Instructors
- Dive Course Booking for Students
- Dive Course Booking for Dive Centers
- Dive Course Booking for Dive Schools
- Dive Course Booking for Dive Resorts

#### Installation
- Clone the repository
- Run `composer install`

#### Configuration
- Create a `.env` file
- Copy the content of `.env.example` into `.env`
- Run `php artisan

#### Form and dialog translations

Forms, confirmation dialogs and their planning/attendance interactions support
German, English, French and Spanish. German remains the default locale with an
English fallback. The existing administration language selector includes all
four languages; no additional public language selector is introduced.

`translations/messages.{de,en,fr,es}.php` are native Symfony PHP catalogues.
Their shared vocabulary is in `translations/form_catalogues.php`: German
source messages have English/French/Spanish columns, while parameterized
messages have explicit four-language rows. Aliases cover existing English
labels and Symfony's inferred field labels. Form labels, help, placeholders,
static choices and dialog copy use `messages`; validation constraints use
`validators`; login errors use `security`. Standard validator, security,
EasyAdmin and password/email verification text uses the installed bundles'
catalogues. Names, titles and other user-entered/entity data are not translated.

Rich-text form widgets are included: the existing public TinyMCE 7 editor uses
local, pinned German/French/Spanish language packs in `public/js/tinymce/langs/`
(English is built in; provenance and licenses are in `LICENSE.txt`). EasyAdmin's
Trix toolbar, link dialog and attachment captions use the same Symfony message
catalogue through `TextEditorTranslationConfigurator`, preserving explicit
per-field editor configuration.

Use named parameters rather than formatting a message before translating it.
Flash messages containing parameters use `TranslatableMessage`. Inline
JavaScript translations are JSON-encoded; confirmation arguments are also
HTML-attribute-escaped. Keep new form labels and choices in all four locales.

The regression runner uses the installed Symfony commands without booting the
application, accessing a database, or writing caches/logs:

```sh
php tests/translation_health.php
php tests/translation_health.php lint:twig templates
php tests/translation_health.php lint:yaml translations
php tests/translation_health.php lint:translations
php tests/translation_health.php debug:translation en --domain=messages --only-missing
php tests/translation_health.php debug:translation fr --domain=messages --only-missing
php tests/translation_health.php debug:translation es --domain=messages --only-missing
```

The runner substitutes database-backed choices only for testing and checks
every application FormType, catalogue/parameter parity, actual form and
validation translations, admin labels/help/actions, confirmation escaping,
locale-rendered JavaScript and rich-text editor translations.

#### License
- [GNU](https://www.gnu.org/licenses/gpl-3.0.html)

#### Author
- [Diversworld](https://diversworld.eu) - [GitHub](https://github.com/diversworld/dicoma)

#### Version
- 1.0.0

```
