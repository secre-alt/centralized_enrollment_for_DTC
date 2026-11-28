<?php

namespace Tests\Unit;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class PdfResourceSecurityTest extends TestCase
{
    public function test_pdf_renderer_disables_remote_resources_and_php_while_rendering_local_content(): void
    {
        $options = Pdf::getDomPDF()->getOptions();
        $this->assertFalse($options->getIsRemoteEnabled());
        $this->assertFalse($options->getIsPhpEnabled());
        $this->assertStringStartsWith('%PDF-', Pdf::loadHTML('<p>Local document</p>')->output());
    }
}
