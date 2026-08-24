<?php

namespace Tests\Unit;

use App\Models\AccreditationDocument;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccreditationDocumentIpTest extends TestCase
{
    #[Test]
    public function uploader_ip_is_hashed_before_it_is_stored(): void
    {
        config(['app.key' => 'base64:test-key-for-ip-hashing']);

        $document = new AccreditationDocument([
            'uploader_ip_hash' => AccreditationDocument::hashUploaderIp('192.0.2.10'),
        ]);

        $this->assertNotSame('192.0.2.10', $document->uploader_ip_hash);
        $this->assertStringNotContainsString('192.0.2.10', $document->uploader_ip_hash);
    }
}
