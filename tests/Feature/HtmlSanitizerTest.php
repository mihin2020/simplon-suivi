<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Learner;
use App\Models\LearnerInterview;
use App\Support\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_scripts_and_dangerous_elements_are_removed_with_their_content(): void
    {
        $html = '<p>Bonjour</p><script>alert(1)</script><style>p{x:y}</style><iframe src="https://evil.example"></iframe>'
            .'<svg onload="alert(1)"><circle/></svg><form action="/x"><input name="a"></form><noscript><img src=x onerror=alert(1)></noscript>';

        $clean = HtmlSanitizer::clean($html);

        $this->assertSame('<p>Bonjour</p>', $clean);
    }

    public function test_every_attribute_is_stripped(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="alert(1)" style="x:y" class="a" id="b">Texte</p><div onmouseover="alert(1)">Bloc</div>');

        $this->assertSame('<p>Texte</p><div>Bloc</div>', $clean);
    }

    public function test_dangerous_link_schemes_are_dropped_even_when_disguised(): void
    {
        foreach ([
            'javascript:alert(1)',
            "java\tscript:alert(1)",
            ' JaVaScRiPt:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            '//evil.example/x',
            '/relative/path',
        ] as $href) {
            $clean = HtmlSanitizer::clean('<a href="'.htmlspecialchars($href).'">lien</a>');

            $this->assertStringNotContainsString('href', $clean, $href);
            $this->assertStringContainsString('lien', $clean);
        }
    }

    public function test_safe_links_are_kept_and_hardened(): void
    {
        $clean = HtmlSanitizer::clean('<a href="https://example.org/page?a=1&amp;b=2" target="_self" onclick="x()">Voir</a><a href="mailto:a@b.test">Mail</a>');

        $this->assertStringContainsString('href="https://example.org/page?a=1&amp;b=2"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
        $this->assertStringContainsString('href="mailto:a@b.test"', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    public function test_images_are_dropped_unless_explicitly_allowed(): void
    {
        $html = '<p>A</p><img src="https://tracker.example/pixel.gif" onerror="alert(1)" alt="x">';

        $this->assertSame('<p>A</p>', HtmlSanitizer::clean($html));
        $this->assertSame('<p>A</p><img src="https://tracker.example/pixel.gif" alt="x">', HtmlSanitizer::clean($html, allowImages: true));
    }

    public function test_image_sources_other_than_http_are_dropped(): void
    {
        foreach (['javascript:alert(1)', 'data:image/svg+xml;base64,PHN2Zz4=', 'file:///etc/passwd', '/local.png'] as $src) {
            $this->assertSame('', HtmlSanitizer::clean('<img src="'.$src.'">', allowImages: true), $src);
        }
    }

    public function test_comments_and_unknown_tags_are_neutralized_but_text_is_kept(): void
    {
        $clean = HtmlSanitizer::clean('<!--[if mso]><script>alert(1)</script><![endif]--><custom-tag onclick="x()"><b>gras</b> texte</custom-tag>');

        $this->assertSame('<b>gras</b> texte', $clean);
    }

    public function test_empty_input_gives_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }

    public function test_received_email_body_is_sanitized_for_display(): void
    {
        $email = new Email(['body_html' => '<p>Salut</p><script>alert(1)</script>', 'body_text' => 'ignored']);

        $this->assertSame('<p>Salut</p>', $email->safe_body_html);
    }

    public function test_text_only_email_is_escaped(): void
    {
        $email = new Email(['body_html' => null, 'body_text' => "<script>alert(1)</script>\nLigne 2"]);

        $this->assertStringNotContainsString('<script>', $email->safe_body_html);
        $this->assertStringContainsString('&lt;script&gt;', $email->safe_body_html);
        $this->assertStringContainsString('<br', $email->safe_body_html);
    }

    public function test_interview_rich_text_is_served_sanitized(): void
    {
        $learner = Learner::factory()->create();
        $interview = LearnerInterview::factory()->create([
            'learner_id' => $learner->id,
            'notes' => '<p>Note</p><script>alert(1)</script>',
            'recommendation' => '<p onclick="x()">Suite</p>',
        ]);

        $fresh = $interview->fresh();

        $this->assertSame('<p>Note</p>', $fresh->notes);
        $this->assertSame('<p>Suite</p>', $fresh->recommendation);
        $this->assertStringNotContainsString('script', json_encode($fresh->toArray()));
    }
}
