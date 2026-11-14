# **Why a free plugin can be rejected by the WordPress.org Plugin Directory**

WordPress.org does not automatically accept every free, functional WordPress plugin. A plugin must satisfy its security, coding, licensing, privacy, business-model, presentation and directory policies.

There are two important distinctions:

1. **Technical rejection:** The plugin has security vulnerabilities, coding problems or compatibility issues.
2. **Policy rejection:** The plugin's purpose, business model, distribution method or behaviour conflicts with WordPress.org rules.

Below is a consolidated checklist of the reasons a submission can be rejected, delayed or subsequently removed.

## **1. Security and coding problems**

These are among the most common reasons for rejection.

- **Unescaped output:** Printing user-controlled or database content without appropriate escaping (`esc_html()`, `esc_attr()`, `esc_url()`, etc.).
- **Unsanitized input:** Accepting `$_POST`, `$_GET`, `$_REQUEST` or other external input without sanitization and validation.
- **Missing nonces:** Processing forms, AJAX requests or other state-changing operations without appropriate CSRF protection.
- **Missing capability checks:** Allowing administrative actions without checking permissions using `current_user_can()`.
- **Unsafe database queries:** Using variable input in SQL without `$wpdb->prepare()` or appropriate query APIs.
- **Cross-site scripting (XSS):** Allowing untrusted content to execute JavaScript.
- **SQL injection:** Constructing unsafe SQL queries.
- **Insecure file operations:** Unsafe uploads, downloads, file inclusion, deletion or path handling.
- **Direct PHP file access:** Plugin files accessible directly without appropriate protection.
- **Unsafe AJAX and REST endpoints:** Missing authentication, authorization, validation or permission callbacks.
- **Unsafe deserialization:** Untrusted data passed into potentially dangerous deserialization operations.
- **Remote code execution:** Executing untrusted or externally supplied code.
- **Exposing sensitive information:** Leaking credentials, API keys, personal information or internal system details.
- **Insecure external communication:** Sending sensitive data without appropriate transport security.

A plugin does not have to contain an obvious vulnerability to be flagged. Poor security practices can also trigger review feedback.

Official reference: [Common security and coding issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/?utm_source=chatgpt.com).&#x20;

[image](https://www.google.com/s2/favicons?domain=https://wordpress.org\&sz=32)

WordPress.org+1



## **2. Licensing and copyright violations**

- Plugin code must be GPL-compatible.
- Third-party PHP libraries must have compatible licenses.
- JavaScript libraries must have compatible licenses.
- Images, icons, fonts and other bundled assets must have compatible licenses.
- Copying another developer's plugin and presenting it as original work is not permitted.
- Using copyrighted code or assets without appropriate permission is prohibited.
- Violating third-party API or service terms is not acceptable.
- Trademark infringement in the plugin name, slug or branding can delay or prevent approval.

A GPL declaration in the main plugin file does not automatically make every bundled asset GPL-compatible.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **3. Paid features, trialware and monetisation**

This is particularly important because a plugin can be free to download but still violate directory rules through its functionality.

**Not permitted:**

- Locking functionality already included in the submitted plugin behind payment.
- Releasing a limited trial that becomes disabled after a certain period.
- Restricting functionality after a usage quota is reached.
- Providing a sandbox-only or test-only version of a service.
- Distributing a plugin that primarily exists to validate a licence key for locally included premium functionality.
- Hiding functionality that is already present in the plugin until the user purchases an upgrade.

**Permitted in principle:**

- A genuinely useful free plugin that connects to a paid external SaaS service.
- Paid functionality provided by a substantive external service.
- Separate premium add-on plugins distributed outside WordPress.org.
- Reasonable promotion of related paid products.

The distinction is that WordPress.org allows serviceware, but not trialware disguised as a free plugin.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **4. Privacy and undisclosed data collection**

A plugin cannot silently communicate with external servers for unrelated purposes.

Potential rejection reasons:

- Sending usage statistics without explicit consent.
- Collecting website information automatically.
- Collecting personal information without clearly explaining why.
- Sending site URLs, email addresses, user details or other data to the developer's server without authorization.
- Hidden telemetry or analytics.
- Loading unrelated scripts, images or assets from external servers.
- Using third-party advertising or referral tracking without appropriate consent.
- Using external blocklists or data sources without documenting them.
- Making registration or account creation misleadingly mandatory when it is not genuinely necessary.

If a plugin communicates with an external API, explain what information is transmitted, where it goes and why.

An actual SaaS integration is treated differently from undisclosed tracking.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources

## **5. External code, APIs and update mechanisms**

WordPress.org places restrictions on how plugins obtain and execute external code.

Potential rejection reasons:

- Downloading PHP files from an external server and executing them.
- Downloading and installing premium versions of the same plugin from another server.
- Installing other plugins or themes through an external update mechanism.
- Loading JavaScript or CSS from third-party CDNs when it should be bundled locally.
- Using external servers to deliver executable functionality disguised as an ordinary data service.
- Using iframes to connect administrative interfaces when APIs should be used.
- Using undocumented third-party services.
- Using APIs in ways that violate their terms of service.

**Important distinction:** Calling an external API is not inherently prohibited. A plugin can legitimately communicate with external services, including paid SaaS platforms. The concern is external code execution, undisclosed behaviour and misuse of the service model.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **6. Plugin functionality and purpose**

A plugin can be rejected even when its code is technically sound.

WordPress.org does not accept:

- Plugins with no meaningful or practical functionality.
- Incomplete plugins or placeholder submissions.
- Plugins that are essentially copies of existing plugins.
- Plugins that duplicate WordPress Core functionality without meaningful differentiation.
- Framework-only or library-only plugins.
- Plugins that exist primarily to install other plugins.
- Plugins that require other plugins or themes to modify their own source code to work.
- Storefronts that simply display products for purchase from external systems.
- Black-hat SEO tools.
- Content spinners and fake-content generators.
- Spam-generating tools.
- Scrapers intended for harmful or abusive purposes.
- Plugins facilitating illegal activities, violence, hate or other harmful behaviour.

There is also an important practical consideration: if hundreds of similar plugins already exist, WordPress.org may reject a new submission unless it offers meaningful differentiation or substantial innovation.

That last point is particularly relevant to developers building yet another plugin in an established category.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **7. Admin dashboard abuse and advertising**

WordPress.org expects plugins to integrate reasonably with the existing WordPress administration experience.

Reasons for rejection can include:

- Persistent, intrusive upgrade notices.
- Dashboard-wide advertising.
- Repeated promotional popups.
- Notices that cannot be dismissed.
- Notices that remain after the underlying problem is resolved.
- Error messages that do not explain how to fix the problem.
- Excessive promotional widgets.
- Affiliate or referral tracking embedded in dashboard advertisements.

A plugin can promote its own premium products. However, promotional messages should be limited, contextual and non-intrusive.

There is a specific rule concerning frontend branding:

- A developer cannot force a visible "Powered by" link onto the user's public-facing website.
- Credits and external links must be optional.
- The default must be not to display them.
- Users must explicitly opt in.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **8. Readme, directory listing and SEO spam**

The plugin's public listing is also reviewed.

Potential rejection reasons:

- Keyword stuffing in the description.
- Excessive repetition of keywords.
- Using competitor plugin names as tags.
- More than five tags.
- Unnecessary affiliate links.
- Misleading descriptions or claims.
- Promotional content unrelated to the plugin.
- Misleading claims about what the plugin can accomplish.
- Using the readme primarily for search-engine manipulation.

The readme should explain the actual functionality to human users.

Also, claims that a plugin guarantees legal compliance or can automatically ensure regulatory compliance are specifically problematic.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources

## **9. WordPress coding standards and compatibility**

These are common review issues, although not every deviation from a coding convention automatically results in rejection.

Examples:

- Missing prefixes for functions, classes, constants and other global identifiers.
- Generic function names that risk conflicts with other plugins.
- Bundling libraries already included in WordPress, such as jQuery or PHPMailer.
- Not using WordPress APIs where appropriate.
- Incorrect use of `wp_enqueue_script()` and `wp_enqueue_style()`.
- Loading scripts and styles globally when only specific admin pages need them.
- Incorrect internationalization and text-domain implementation.
- Incompatible PHP syntax or unsupported PHP versions.
- Poor handling of WordPress hooks and filters.
- Direct database manipulation where WordPress APIs should be used.
- Incorrect handling of HTTP requests.
- Unnecessary dependencies.
- Unnecessary or undocumented code.

For example, if WordPress already provides a library, you should generally use the WordPress-provided version instead of bundling your own.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **10. Submission packaging and completeness**

The submitted ZIP must be ready for real-world installation.

Reasons for rejection or submission delays:

- ZIP file exceeds 10 MB.
- Incorrect plugin directory structure.
- Missing main plugin PHP file or plugin headers.
- Missing dependencies or compiled assets.
- Development-only files included unnecessarily.
- Placeholder functionality.
- Debug output, unnecessary logging or PHP errors.
- Missing production JavaScript/CSS build files.
- Plugin requires manual code modification before it can work.
- Plugin is incomplete or not production-ready.
- Incorrect `readme.txt` format or metadata.

The submission should contain the actual installable plugin, not a development project.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **11. Plugin name, slug and ownership**

These issues can prevent approval even before the code receives a full review.

- Using another company's trademark as the first or sole term in the plugin name.
- Using WordPress or another protected project name improperly.
- Using an existing plugin's slug.
- Using a slug already associated with a significant externally distributed plugin, potentially causing update conflicts.
- Impersonating an official plugin or company.
- Submitting an official company plugin through an account that does not demonstrate authorization.
- Attempting to circumvent trademark restrictions through spelling variations.
- Trying to reserve a plugin name without submitting a complete plugin.

One important detail: the plugin slug generally cannot be changed after approval, so it is worth checking before submission.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **12. Developer behaviour and directory abuse**

The developer's conduct also matters.

Possible grounds for rejection or removal:

- Fake reviews or support tickets.
- Manipulating reviews or pressuring users to leave positive reviews.
- Harassing other developers.
- Threatening or abusing WordPress community members.
- Misrepresenting plugin ownership.
- Repeatedly violating plugin guidelines.
- Intentionally restoring code that reviewers instructed the developer to remove.
- Attempting to exploit loopholes in directory policies.
- Violating WordPress community or forum guidelines.
- Falsifying identity to avoid previous sanctions.

Repeated violations can result in all of a developer's plugins being removed and the developer being banned from hosting plugins.

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1

## **13. Post-approval reasons for removal**

Approval is not permanent immunity from review.

An already published plugin can be closed or removed because of:

- Newly discovered security vulnerabilities.
- Copyright or trademark complaints.
- Privacy violations.
- New violations introduced through an update.
- Malicious code or suspicious behaviour.
- Undisclosed tracking.
- Violations of monetisation policies.
- Abandoned plugins.
- Broken SVN repositories.
- Developer misconduct.
- Author-requested closure.

There is also an important newer development: since June 2026, WordPress.org has introduced an automated security review and cooldown process for plugin releases. High-risk releases can be blocked from distribution until problems are resolved. This is separate from the initial submission review.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1



## **Practical pre-submission checklist**

Here is how I would prioritise an independent developer's review before uploading a plugin.

**Pre-submission audit**

0/29 checked

**Security**

Sanitize and validate every input

Escape every dynamic output

Nonce verification for state-changing requests

Capability checks on administrative actions

Prepared SQL queries

Secure AJAX and REST endpoints

Safe file handling and uploads

No direct execution of PHP files

**Directory compliance**

GPL-compatible code and bundled assets

No trialware or locally locked features

No unauthorized tracking or telemetry

No external executable code downloads

No forced frontend branding

No intrusive admin notices or advertising

No trademark or slug conflicts

No copied or misleading functionality

**WordPress development**

Unique prefixes for global identifiers

Use WordPress-provided libraries

Correct enqueueing of scripts and styles

Correct text domain and translations

Compatible PHP and WordPress implementation

Use WordPress APIs appropriately

**Submission**

Complete and production-ready ZIP

ZIP under 10 MB

Correct plugin headers and readme.txt

No development tools or unnecessary files

All dependencies and assets included

Clear description and honest feature claims

Working demo or documentation where appropriate

Reset checklist

## **Official references worth bookmarking**

1. [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/?utm_source=chatgpt.com) – The actual directory policies.
2. [Common Issues](https://developer.wordpress.org/plugins/wordpress-org/common-issues/?utm_source=chatgpt.com) – Examples of coding and security problems reviewers encounter.
3. [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/?utm_source=chatgpt.com) – Submission, functionality, ownership and naming restrictions.
4. [Add Your Plugin](https://wordpress.org/plugins/developers/add/?utm_source=chatgpt.com) – Submission requirements and the most common rejection reasons.
5. [Automated Security Review](https://developer.wordpress.org/plugins/wordpress-org/automated-security-review/?utm_source=chatgpt.com) – New release security checks.

**My main takeaway:** There are three particularly important areas to get right: security implementation, the distinction between permissible monetisation and prohibited trialware, and whether the plugin provides legitimate standalone functionality.

Also, WordPress.org explicitly reserves discretion to reject plugins for reasons not individually enumerated in its guidelines. Passing a coding-standard checker does not guarantee approval.&#x20;

[image](https://www.google.com/s2/favicons?domain=https://developer.wordpress.org\&sz=32)

WordPress Developer Resources+1