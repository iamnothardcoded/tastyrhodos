<?php

declare(strict_types=1);

namespace Jamasa\Core\Tests;

use Jamasa\Core\Helpers\EmailNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function guard for the account-identity canonicalizer (the
 * welcome-discount farming shield). Over-merging distinct mailboxes is a
 * cross-customer data leak, so the "kept" cases matter as much as the "folded".
 */
final class EmailNormalizerTest extends TestCase
{
    public function test_gmail_dots_and_tags_fold_to_one_identity(): void
    {
        $this->assertSame('maxmuster@gmail.com', EmailNormalizer::normalize('Max.Muster@gmail.com'));
        $this->assertSame('maxmuster@gmail.com', EmailNormalizer::normalize('maxmuster+promo@gmail.com'));
        $this->assertSame('maxmuster@gmail.com', EmailNormalizer::normalize('M.A.X.Muster+a+b@GMAIL.com'));
    }

    public function test_googlemail_maps_to_gmail(): void
    {
        $this->assertSame('maxmuster@gmail.com', EmailNormalizer::normalize('max.muster@googlemail.com'));
    }

    public function test_known_provider_tags_are_stripped(): void
    {
        $this->assertSame('anna@web.de', EmailNormalizer::normalize('anna+shop@web.de'));
        $this->assertSame('anna@outlook.com', EmailNormalizer::normalize('anna+x@outlook.com'));
    }

    public function test_non_gmail_dots_are_kept(): void
    {
        // Dots are RFC-significant outside gmail — must NOT be stripped.
        $this->assertSame('a.b@web.de', EmailNormalizer::normalize('a.b@web.de'));
    }

    public function test_unknown_domain_tags_are_kept_no_over_merge(): void
    {
        // sales+support@firma.de may be a genuinely different mailbox — keep it.
        $this->assertSame('sales+support@firma.de', EmailNormalizer::normalize('Sales+Support@firma.de'));
    }

    public function test_empty_local_part_is_never_produced(): void
    {
        $this->assertSame('+only@gmail.com', EmailNormalizer::normalize('+only@gmail.com'));
    }

    public function test_lowercases_trims_and_passes_through_non_email(): void
    {
        $this->assertSame('a@b.de', EmailNormalizer::normalize('  A@B.de '));
        $this->assertSame('notanemail', EmailNormalizer::normalize('notanemail'));
    }
}
