<?php

namespace Tests\Unit;

use App\Models\AccreditationDocument;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccreditationDocumentIpTest extends TestCase
{
    #[Test]
    public function only_the_same_ip_hash_can_delete_a_public_upload(): void
    {
        config(['app.key' => 'base64:test-key-for-ip-hashing']);

        $document = new AccreditationDocument([
            'uploader_ip_hash' => AccreditationDocument::hashUploaderIp('192.0.2.10'),
        ]);

        $this->assertTrue($document->canBeDeletedFromIp('192.0.2.10'));
        $this->assertFalse($document->canBeDeletedFromIp('192.0.2.11'));
        $this->assertNotSame('192.0.2.10', $document->uploader_ip_hash);
        $this->assertStringNotContainsString('192.0.2.10', $document->uploader_ip_hash);
    }
}
