<?php
declare(strict_types=1);

namespace ElanRegistry;

/**
 * EmailTemplate - Centralized Email Template System
 *
 * Provides consistent email formatting across all registry email functionality.
 * Supports branded HTML emails with responsive design and customizable content.
 *
 * @example
 * $template = new EmailTemplate();
 * $content = $template->createMessageBox('Title', $template->createDetailRow('Name', $value));
 * echo $template->render('Subject', 'Subtitle', $content);
 *
 * Escaping contract (per method):
 * - createDetailRow()     — escapes both $label and $value, always, regardless of $highlighted
 * - createRawDetailRow()  — escapes $label only; $trustedHtml is caller-trusted and NOT escaped
 * - createMessageContent()— escapes $text
 * - createButton()        — escapes both $text and $url
 * - createButtonRow()     — escapes 'label' and 'url' for every button entry
 * - createMessageBox()    — escapes $title only; $content is raw HTML BY DESIGN and NOT escaped
 *
 * Callers passing user-supplied data into a non-escaping parameter ($content,
 * $trustedHtml) are entirely responsible for escaping it first.
 *
 * @author Elan Registry Team
 * @copyright 2025
 */

class EmailTemplate
{
    private string $baseUrl;
    private string $logoUrl;

    public function __construct()
    {
        $this->baseUrl = getBaseUrl();
        $this->logoUrl = $this->baseUrl . '/usersc/images/logo-72x72.png';
    }

    /**
     * Generate a complete HTML email using the registry template
     *
     * @param string $subject HTML document `<title>` only — does NOT set the SMTP subject. Pass the SMTP subject separately when calling sendinblue() or email().
     * @param string $subtitle Header subtitle shown in the email header bar (e.g., "Owner to Owner Message")
     * @param string $content Main content HTML (can include custom styling)
     * @param array $options Optional customizations ['footer_text' => ''] Note: reply-to must be set at the transport layer, not via this method.
     * @return string Complete HTML email
     */
    public function render(string $subject, string $subtitle, string $content, array $options = []): string
    {
        $footerText = $options['footer_text'] ?? 'This is an automated message from the registry system.';
        return $this->getBaseTemplate($subject, $subtitle, $content, $footerText);
    }

    /**
     * Create a formatted message box for email content
     *
     * @param string $title Box title
     * @param string $content Pre-composed HTML — not escaped. Use createDetailRow() and createMessageContent() to build this safely from user data.
     * @param string $style 'default', 'message', 'alert', 'success'
     * @return string HTML for message box
     */
    public function createMessageBox(string $title, string $content, string $style = 'default'): string
    {
        $styleClass = $this->getBoxStyleClass($style);
        $inlineStyles = $this->getBoxInlineStyles($style);
        $headingColor = $this->getBoxAccentColor($style);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        return "
        <div class=\"{$styleClass}\" style=\"{$inlineStyles}\">
            <h3 style=\"color: {$headingColor}; margin-top: 0;\">{$safeTitle}</h3>
            <div class=\"detail-section\" style=\"background-color: #ffffff; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin: 15px 0;\">
                {$content}
            </div>
        </div>";
    }

    /**
     * Create a details row for displaying key-value information
     *
     * Both $label and $value are HTML-escaped unconditionally — the
     * $highlighted flag only changes cell styling, never escaping.
     *
     * @param string $label Field label (escaped)
     * @param string $value Field value (escaped)
     * @param bool $highlighted Apply an emphasis band (tinted background, left accent) to both cells
     * @return string HTML for detail row
     */
    public function createDetailRow(string $label, string $value, bool $highlighted = false): string
    {
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // Cell-level background rather than table-level: Outlook's Word rendering
        // engine drops table-level background-color far more often than td-level.
        $labelStyles = $highlighted
            ? 'font-weight: bold; width: 120px; color: #469408; vertical-align: top; background-color: #FFF9E0; border-left: 4px solid #B8860B; padding: 8px 10px 8px 8px;'
            : 'font-weight: bold; width: 120px; color: #469408; vertical-align: top; padding-right: 10px;';
        $valueStyles = $highlighted
            ? 'vertical-align: top; background-color: #FFF9E0; padding: 8px 10px 8px 0;'
            : 'vertical-align: top;';

        return "
        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"margin-bottom: 10px;\">
            <tr>
                <td style=\"{$labelStyles}\">" . $safeLabel . ":</td>
                <td style=\"{$valueStyles}\">" . $safeValue . "</td>
            </tr>
        </table>";
    }

    /**
     * Create a details row whose value is pre-composed, trusted HTML.
     *
     * UNLIKE createDetailRow(), $trustedHtml is NOT escaped — it is emitted
     * verbatim. The caller is entirely responsible for escaping any
     * user-supplied data embedded in $trustedHtml before passing it here. Use
     * this only for content you control (e.g. a composed <img> tag, an
     * internal link) — never pass raw user input.
     *
     * @param string $label Field label (escaped)
     * @param string $trustedHtml Pre-composed HTML, NOT escaped — caller-trusted
     * @return string HTML for detail row
     */
    public function createRawDetailRow(string $label, string $trustedHtml): string
    {
        return "
        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"margin-bottom: 10px;\">
            <tr>
                <td style=\"font-weight: bold; width: 120px; color: #469408; vertical-align: top; padding-right: 10px;\">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ":</td>
                <td style=\"vertical-align: top;\">" . $trustedHtml . "</td>
            </tr>
        </table>";
    }

    /**
     * Create a styled pre-wrapped message content block for free-form user text.
     *
     * Use this inside createMessageBox() $content when displaying raw user-supplied
     * text (messages, comments, notes). Handles HTML escaping internally.
     *
     * @param string $text   Raw user-supplied text (will be HTML-escaped)
     * @param bool   $italic Apply italic style (e.g., for quoted feedback)
     * @return string HTML div with message-content styling
     */
    public function createMessageContent(string $text, bool $italic = false): string
    {
        $extraStyle = $italic ? ' font-style:italic;' : '';
        return '<div class="message-content" style="background-color:#f8f9fa; border-left:4px solid #469408; '
            . 'padding:15px; margin:10px 0; white-space:pre-wrap;' . $extraStyle . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    /**
     * Create an action button for emails
     *
     * @param string $text Button text
     * @param string $url Button URL
     * @param string $style 'primary', 'secondary', 'success', 'danger'
     * @return string HTML for button
     */
    public function createButton(string $text, string $url, string $style = 'primary'): string
    {
        $buttonClass = 'btn btn-' . $style;
        $inlineStyles = $this->getButtonInlineStyles($style);
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return "
        <div class=\"text-center\" style=\"text-align: center;\">
            <a href=\"{$safeUrl}\" class=\"{$buttonClass}\" style=\"{$inlineStyles}\">{$safeText}</a>
        </div>";
    }

    /**
     * Create a row of two or more side-by-side action buttons
     *
     * Table-based (rather than createButton()'s div wrapper) so email clients
     * lay the buttons out side by side reliably. Collapses to stacked
     * full-width buttons below the template's 600px responsive breakpoint.
     *
     * 'label' and 'url' are runtime-validated (not just type-hinted) because
     * callers may build $buttons from conditional/looped logic where a key
     * can legitimately be omitted at the array-shape level; a missing key
     * throws rather than rendering a broken button.
     *
     * @param array<int, array{label?: string, url?: string, style?: string}> $buttons Button definitions; 'label' and 'url' required per entry, 'style' defaults to 'primary'
     * @return string HTML for button row
     * @throws \InvalidArgumentException When fewer than two buttons are supplied,
     *                                    or any button entry is missing 'label' or 'url'
     */
    public function createButtonRow(array $buttons): string
    {
        if (count($buttons) < 2) {
            throw new \InvalidArgumentException(
                'createButtonRow() requires at least 2 buttons; use createButton() for a single button.'
            );
        }

        $cells = [];
        foreach ($buttons as $i => $button) {
            if (!isset($button['label'], $button['url'])) {
                throw new \InvalidArgumentException(
                    "createButtonRow(): button at index {$i} must have both 'label' and 'url'."
                );
            }
            $style        = $button['style'] ?? 'primary';
            $inlineStyles = $this->getButtonInlineStyles($style);
            // htmlspecialchars() also protects the class attribute below: $style is
            // developer-supplied (never user input) but is interpolated unescaped
            // into class="btn btn-{$style}" in createButton() itself, so escaping
            // it here — even though this method doesn't reuse that exact line —
            // keeps createButtonRow() from reproducing that same injection shape.
            $buttonClass  = 'btn btn-' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8');
            $safeUrl      = htmlspecialchars($button['url'], ENT_QUOTES, 'UTF-8');
            $safeLabel    = htmlspecialchars($button['label'], ENT_QUOTES, 'UTF-8');
            $cells[] = "
                    <td class=\"btn-row-cell\" style=\"padding: 5px 8px;\">
                        <a href=\"{$safeUrl}\" class=\"{$buttonClass}\" style=\"{$inlineStyles}\">{$safeLabel}</a>
                    </td>";
        }
        $cellsHtml = implode('', $cells);

        return "
        <style>
            @media only screen and (max-width: 600px) {
                .btn-row-cell {
                    display: block;
                    width: 100%;
                    padding: 5px 0;
                }
            }
        </style>
        <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin: 10px auto; text-align: center;\">
            <tr>{$cellsHtml}
            </tr>
        </table>";
    }

    // ---------------------------------------------------------------
    // Message box style helpers
    // ---------------------------------------------------------------

    /**
     * Get CSS class for message box style
     *
     * @param string $style Box style: 'message', 'alert', 'success', or 'default'
     * @return string CSS class string
     */
    private function getBoxStyleClass(string $style): string
    {
        return match ($style) {
            'message' => 'content-box content-box-message',
            'alert'   => 'content-box content-box-alert',
            'success' => 'content-box content-box-success',
            default   => 'content-box',
        };
    }

    /**
     * Get accent color for message box based on style type
     *
     * @param string $style Box style: 'message', 'alert', 'success', or 'default'
     * @return string CSS color hex value
     */
    private function getBoxAccentColor(string $style): string
    {
        $colors = [
            'message' => '#469408',
            'alert'   => '#dc3545',
            'success' => '#28a745',
            'default' => '#00563F',
        ];
        return $colors[$style] ?? $colors['default'];
    }

    /**
     * Get inline CSS styles for message box based on style type
     *
     * @param string $style Box style: 'message', 'alert', 'success', or 'default'
     * @return string Inline CSS style string
     */
    private function getBoxInlineStyles(string $style): string
    {
        $color = $this->getBoxAccentColor($style);
        return "background-color: #f8f9fa; border: 2px solid {$color}; padding: 20px; margin: 20px 0;";
    }

    // ---------------------------------------------------------------
    // Button style helpers
    // ---------------------------------------------------------------

    /**
     * Get inline CSS styles for button based on style type
     *
     * @param string $style Button style: 'primary', 'secondary', 'success', or 'danger'
     * @return string Inline CSS style string
     */
    private function getButtonInlineStyles(string $style): string
    {
        $colors = [
            'primary'   => '#00563F',
            'secondary' => '#6c757d',
            'success'   => '#28a745',
            'danger'    => '#dc3545',
        ];
        $bg = $colors[$style] ?? $colors['primary'];
        return "display: inline-block; background-color: {$bg}; color: #ffffff; padding: 12px 24px; text-decoration: none; font-weight: bold; text-align: center;";
    }

    // ---------------------------------------------------------------
    // HTML template
    // ---------------------------------------------------------------

    /**
     * Build the complete HTML email template
     *
     * @param string $title HTML document title
     * @param string $subtitle Header subtitle shown in the email header bar
     * @param string $content Pre-composed HTML body content — not escaped here
     * @param string $footerText Footer note (escaped before output)
     * @return string Complete HTML email
     */
    private function getBaseTemplate(string $title, string $subtitle, string $content, string $footerText): string
    {
        global $settings;

        $siteName    = htmlspecialchars($settings->site_name ?? 'Lotus Elan Registry', ENT_QUOTES, 'UTF-8');
        $safeTitle   = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeFooter  = htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8');
        $safeBaseUrl = htmlspecialchars($this->baseUrl, ENT_QUOTES, 'UTF-8');
        $safeLogoUrl = htmlspecialchars($this->logoUrl, ENT_QUOTES, 'UTF-8');
        // NOTE: $content is trusted pre-composed HTML. Callers are responsible for
        // escaping all user-supplied values before including them in $content.
        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$siteName} - {$safeTitle}</title>
    <style>
        /* Base Styles */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        /* Header Styles */
        .header {
            background-color: #00563F;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .logo {
            width: 48px;
            height: 48px;
            margin-bottom: 10px;
        }

        /* Content Styles */
        .content {
            padding: 30px;
        }

        .content-box {
            background-color: #f8f9fa;
            border: 2px solid #00563F;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .content-box-message {
            border-color: #469408;
        }

        .content-box-alert {
            border-color: #dc3545;
        }

        .content-box-success {
            border-color: #28a745;
        }

        .content-box h3 {
            color: #00563F;
            margin-top: 0;
        }

        .content-box-message h3 {
            color: #469408;
        }

        .content-box-alert h3 {
            color: #dc3545;
        }

        .content-box-success h3 {
            color: #28a745;
        }

        .detail-section {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }

        .message-content {
            background-color: #f8f9fa;
            border-left: 4px solid #469408;
            padding: 15px;
            margin: 20px 0;
            white-space: pre-wrap;
        }

        /* Button Styles */
        .btn {
            display: inline-block;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            margin: 10px 5px;
        }

        .btn-primary {
            background-color: #00563F;
            color: #ffffff;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #ffffff;
        }

        .btn-success {
            background-color: #28a745;
            color: #ffffff;
        }

        .btn-danger {
            background-color: #dc3545;
            color: #ffffff;
        }

        /* Footer Styles */
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
            color: #6b7280;
            font-size: 14px;
        }

        /* Utility Classes */
        .lotus-green { color: #469408; }
        .lotus-primary { color: #00563F; }
        .lotus-red { color: #dc3545; }
        .text-center { text-align: center; }

        /* Responsive Design */
        @media only screen and (max-width: 600px) {
            .content {
                padding: 20px;
            }
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"background-color: #f4f4f4;\">
        <tr>
            <td align=\"center\" valign=\"top\">
                <table role=\"presentation\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"width: 600px; background-color: #ffffff;\">
                    <tr>
                        <td>
                            <div class=\"header\" style=\"background-color: #00563F; color: #ffffff; padding: 20px; text-align: center;\">
                                <img src=\"{$safeLogoUrl}\" alt=\"Lotus Logo\" class=\"logo\" style=\"width: 48px; height: 48px; display: block; margin: 0 auto 10px auto;\">
                                <h1 style=\"color: #ffffff; margin: 0 0 5px 0; font-size: 24px;\">{$siteName}</h1>
                                <p>" . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . "</p>
                            </div>

                            <div class=\"content\" style=\"padding: 30px;\">
                                {$content}
                            </div>

                            <div class=\"footer\" style=\"background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6; color: #6b7280; font-size: 14px;\">
                                <p><strong>The {$siteName}</strong></p>
                                <p><a href=\"{$safeBaseUrl}\">{$safeBaseUrl}</a></p>
                                <p>Preserving the legacy of Colin Chapman's masterpiece since 2003</p>
                                <hr style=\"border: none; border-top: 1px solid #dee2e6; margin: 15px 0;\">
                                <p><span style=\"font-size: 12px;\">{$safeFooter}</span></p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
    }
}