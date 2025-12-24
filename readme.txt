=== Fake User Detector ===
Contributors: apos37
Tags: spam, user registration, fake users, bot detection, account flagging
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Detect and flag suspicious existing user accounts using simple checks to help clean up fake or low-quality registrations.

== Description ==

Fake User Detector helps WordPress site owners identify and flag suspicious user accounts after they have already registered.

This plugin does not prevent or block registrations. Instead, it analyzes user data post-registration to highlight accounts that appear automated, fake, or low-quality, making it easier to review and remove them manually.

Fake User Detector is designed as a cleanup and review tool, not a registration firewall. It works well alongside other plugins that handle CAPTCHA, email verification, honeypots, or other signup prevention techniques.

**Features:**

- **Post-Registration Analysis:** Evaluates user accounts after creation to identify suspicious patterns.
- **Gibberish Detection:** Flags accounts with non-human patterns like too many uppercase letters, no vowels, or clusters of consonants.
- **Symbol and Number Filters:** Detects unnatural use of digits or special characters in names.
- **Customizable Detection Rules:** Enable or disable individual checks to suit your site's user base.
- **Flag for Review:** Suspicious accounts are flagged and marked for potential deletion.
- **Admin Notice:** Quickly see how many flagged users exist from your admin area.
- **Scan Existing Users:** Scan the users admin list table for suspicious accounts so you can easily delete them.
- **Gravity Forms Integration:** If using Gravity Forms User Registration, the plugin optionally runs validation checks on registrations submitted via forms.
- **Developer Hooks:** Add or customize detection logic with your own functions.

**Detection Checks Include:**

- Manually flagged by admin
- Excessive uppercase letters (more than 5 in a name unless all caps)
- No vowels in names longer than 5 characters
- Six or more consecutive consonants in a name
- Presence of numbers in names
- Presence of special characters other than letters, numbers, and dashes
- Similarity between first and last name (exact match or one includes the other)
- Very short names (2 characters)
- Invalid or disposable email domains
- Excessive periods in email address (more than 3)
- Username containing URL patterns (`http`, `https`, or `www`)
- Known spam words in user bio or name

Fake User Detector is ideal for membership sites, communities, forums, or any WordPress site that allows user registration and needs a practical way to review and clean up suspicious accounts that already exist.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/fake-user-detector/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure detection settings in the plugin's admin settings page under **Users > Fake User Settings**.
4. Monitor flagged accounts from the WordPress Users screen or run a quick scan from the settings screen.

== Frequently Asked Questions ==

= Does this plugin prevent fake accounts from registering? =
No. Fake User Detector does not block or prevent registrations. It runs after a user account is created and is designed to help you identify, flag, and clean up suspicious or low-quality accounts that already exist.

There are many other plugins that focus on preventing fake registrations at the point of signup (CAPTCHAs, honeypots, email verification, 2FA, etc.). Fake User Detector is intentionally different: it acts as a post-registration analysis and cleanup tool, notifying you of accounts that look suspicious and allowing you to scan existing users against the current detection rules so you can review or remove them efficiently.

= Will this plugin catch all fake or spam accounts? =
No. It is not perfect, and it is not intended to be.

Fake User Detector relies on pattern-based checks that work well for many common fake or automated accounts, but some spam accounts will still look human enough to pass detection. Likewise, legitimate users may occasionally be flagged depending on your settings and user base.

We actively add new detection checks as we encounter new spam patterns on our own sites, and the plugin will continue to evolve over time. For developers, the plugin also provides hooks that allow you to add your own custom detection logic or extend existing checks to better fit your specific needs.

= How does the plugin determine if an account is fake? =
The plugin uses a set of simple checks designed to identify accounts that don’t look like real human registrations. This includes checking for too many uppercase letters, missing vowels, long consonant clusters, symbols, and more.

= Will flagged users be deleted automatically? =
Flagged users are only marked for review, which can easily be filtered and removed from the user admin table.

= Can I disable specific checks? =
Yes. The plugin settings let you turn on or off individual detection checks such as vowel absence or number detection.

= Where can I request features and get support? =
We recommend using our [website support forum](https://pluginrx.com/support/plugin/fake-user-detector/) as the primary method for requesting features and getting help. You can also reach out via our [Discord support server](https://discord.gg/3HnzNEJVnR) or the [WordPress.org support forum](https://wordpress.org/support/plugin/fake-user-detector/), but please note that WordPress.org doesn’t always notify us of new posts, so it’s not ideal for time-sensitive issues.

== Changelog ==
= 1.0.3 =
* Update: Prepare for release on WP Repo
* Fix: Check registration option not enabled by default unless settings have been saved

= 1.0.2 =
* Fix: Spam phrases not parsing correctly
* Update: Added spam words field for easy editing
* Update: Added registration check

= 1.0.1 =
* Initial release