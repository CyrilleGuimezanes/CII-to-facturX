<?php

namespace CiiToFacturX;

/**
 * Generates a PDF/A-3b compliant FacturX PDF with an embedded CII XML file
 * using TCPDF's built-in PDF/A-3 and embedded file support.
 */
class FacturXPdf
{
    /**
     * Generate a FacturX PDF from parsed CII data.
     *
     * @param CiiParser $parser Parsed CII data
     * @return string PDF content as string
     */
    public static function generateFromCii(CiiParser $parser)
    {
        // Create TCPDF with PDF/A-3 mode (constructor param $pdfa=3)
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false, 3);

        // Metadata
        $invoiceNumber = $parser->getInvoiceNumber();
        $seller = $parser->getSeller();
        $profile = $parser->getProfile();

        $pdf->SetCreator('CII-to-FacturX');
        $pdf->SetAuthor($seller['name'] ?: 'CII-to-FacturX');
        $pdf->SetTitle('Invoice ' . $invoiceNumber);
        $pdf->SetSubject('FacturX Invoice ' . $invoiceNumber);
        $pdf->SetKeywords('FacturX, Invoice, ' . $invoiceNumber);

        // Add FacturX XMP extension schema
        $facturxConformance = self::getConformanceLevel($profile);
        $pdf->setExtraXMPPdfaextension(self::getFacturXExtensionSchema());
        $pdf->setExtraXMPRDF(self::getFacturXProperties($facturxConformance));

        // Embed the CII XML as factur-x.xml
        $pdf->EmbedFileFromString('factur-x.xml', $parser->getRawXml());

        // Disable header/footer for simplicity
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Add page
        $pdf->AddPage();

        // Render invoice content
        self::renderInvoice($pdf, $parser);

        // Output PDF as string
        return $pdf->Output('facturx.pdf', 'S');
    }

    /**
     * Map profile name to FacturX conformance level.
     *
     * @param string $profile
     * @return string
     */
    private static function getConformanceLevel($profile)
    {
        $map = [
            'MINIMUM'   => 'MINIMUM',
            'BASIC WL'  => 'BASIC WL',
            'BASIC'     => 'BASIC',
            'EN 16931'  => 'EN 16931',
            'EXTENDED'  => 'EXTENDED',
        ];
        return isset($map[$profile]) ? $map[$profile] : 'EN 16931';
    }

    /**
     * Get the FacturX PDFA extension schema XML for XMP metadata.
     *
     * @return string
     */
    private static function getFacturXExtensionSchema()
    {
        $xmp = '';
        $xmp .= "\t\t\t\t\t" . '<rdf:li rdf:parseType="Resource">' . "\n";
        $xmp .= "\t\t\t\t\t\t" . '<pdfaSchema:schema>Factur-X PDFA Extension Schema</pdfaSchema:schema>' . "\n";
        $xmp .= "\t\t\t\t\t\t" . '<pdfaSchema:namespaceURI>urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#</pdfaSchema:namespaceURI>' . "\n";
        $xmp .= "\t\t\t\t\t\t" . '<pdfaSchema:prefix>fx</pdfaSchema:prefix>' . "\n";
        $xmp .= "\t\t\t\t\t\t" . '<pdfaSchema:property>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t" . '<rdf:Seq>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '<rdf:li rdf:parseType="Resource">' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:name>DocumentFileName</pdfaProperty:name>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:category>external</pdfaProperty:category>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:description>Name of the embedded XML invoice file</pdfaProperty:description>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '</rdf:li>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '<rdf:li rdf:parseType="Resource">' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:name>DocumentType</pdfaProperty:name>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:category>external</pdfaProperty:category>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:description>Type of the hybrid document</pdfaProperty:description>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '</rdf:li>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '<rdf:li rdf:parseType="Resource">' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:name>Version</pdfaProperty:name>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:category>external</pdfaProperty:category>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:description>Version of the Factur-X XML schema</pdfaProperty:description>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '</rdf:li>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '<rdf:li rdf:parseType="Resource">' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:name>ConformanceLevel</pdfaProperty:name>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:valueType>Text</pdfaProperty:valueType>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:category>external</pdfaProperty:category>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t\t" . '<pdfaProperty:description>Conformance level of the Factur-X XML</pdfaProperty:description>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t\t" . '</rdf:li>' . "\n";
        $xmp .= "\t\t\t\t\t\t\t" . '</rdf:Seq>' . "\n";
        $xmp .= "\t\t\t\t\t\t" . '</pdfaSchema:property>' . "\n";
        $xmp .= "\t\t\t\t\t" . '</rdf:li>' . "\n";
        return $xmp;
    }

    /**
     * Get the FacturX properties XMP RDF block.
     *
     * @param string $conformanceLevel
     * @return string
     */
    private static function getFacturXProperties($conformanceLevel)
    {
        $level = \TCPDF_STATIC::_escapeXML($conformanceLevel);
        $xmp = '';
        $xmp .= "\t\t" . '<rdf:Description rdf:about=""' . "\n";
        $xmp .= "\t\t\t" . 'xmlns:fx="urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#">' . "\n";
        $xmp .= "\t\t\t" . '<fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>' . "\n";
        $xmp .= "\t\t\t" . '<fx:DocumentType>INVOICE</fx:DocumentType>' . "\n";
        $xmp .= "\t\t\t" . '<fx:Version>1.0</fx:Version>' . "\n";
        $xmp .= "\t\t\t" . '<fx:ConformanceLevel>' . $level . '</fx:ConformanceLevel>' . "\n";
        $xmp .= "\t\t" . '</rdf:Description>' . "\n";
        return $xmp;
    }

    /**
     * Render invoice content on the PDF.
     *
     * @param \TCPDF    $pdf
     * @param CiiParser $parser
     */
    private static function renderInvoice(\TCPDF $pdf, CiiParser $parser)
    {
        $seller = $parser->getSeller();
        $buyer = $parser->getBuyer();
        $currency = $parser->getCurrency();

        // Title
        $pdf->SetFont('helvetica', 'B', 18);
        $typeCode = $parser->getTypeCode();
        $docType = ($typeCode === '381') ? 'CREDIT NOTE' : 'INVOICE';
        $pdf->Cell(0, 10, $docType, 0, 1, 'C');
        $pdf->Ln(5);

        // Invoice info
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(40, 7, 'Invoice No:', 0, 0);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, $parser->getInvoiceNumber(), 0, 1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(40, 7, 'Date:', 0, 0);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, $parser->getIssueDate(), 0, 1);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(40, 7, 'Profile:', 0, 0);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 7, $parser->getProfile(), 0, 1);

        $pdf->Ln(8);

        // Seller and Buyer side by side
        $startY = $pdf->GetY();

        // Seller (left)
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(85, 7, 'SELLER', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        if (!empty($seller['name'])) {
            $pdf->Cell(85, 6, $seller['name'], 0, 1);
        }
        if (!empty($seller['address'])) {
            $pdf->Cell(85, 6, $seller['address'], 0, 1);
        }
        if (!empty($seller['country'])) {
            $pdf->Cell(85, 6, $seller['country'], 0, 1);
        }
        if (!empty($seller['vat'])) {
            $pdf->Cell(85, 6, 'VAT: ' . $seller['vat'], 0, 1);
        }

        $sellerEndY = $pdf->GetY();

        // Buyer (right)
        $pdf->SetXY(110, $startY);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(85, 7, 'BUYER', 0, 1);
        $pdf->SetX(110);
        $pdf->SetFont('helvetica', '', 10);
        if (!empty($buyer['name'])) {
            $pdf->Cell(85, 6, $buyer['name'], 0, 1);
            $pdf->SetX(110);
        }
        if (!empty($buyer['address'])) {
            $pdf->Cell(85, 6, $buyer['address'], 0, 1);
            $pdf->SetX(110);
        }
        if (!empty($buyer['country'])) {
            $pdf->Cell(85, 6, $buyer['country'], 0, 1);
            $pdf->SetX(110);
        }
        if (!empty($buyer['vat'])) {
            $pdf->Cell(85, 6, 'VAT: ' . $buyer['vat'], 0, 1);
        }

        $pdf->SetY(max($sellerEndY, $pdf->GetY()) + 8);

        // Line items
        $lineItems = $parser->getLineItems();
        if (!empty($lineItems)) {
            // Table header
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(12, 7, '#', 1, 0, 'C', true);
            $pdf->Cell(75, 7, 'Description', 1, 0, 'L', true);
            $pdf->Cell(25, 7, 'Qty', 1, 0, 'R', true);
            $pdf->Cell(30, 7, 'Unit Price', 1, 0, 'R', true);
            $pdf->Cell(38, 7, 'Total (' . $currency . ')', 1, 1, 'R', true);

            // Table rows
            $pdf->SetFont('helvetica', '', 9);
            foreach ($lineItems as $item) {
                $pdf->Cell(12, 6, $item['number'], 1, 0, 'C');
                $pdf->Cell(75, 6, $item['name'], 1, 0, 'L');
                $pdf->Cell(25, 6, $item['quantity'], 1, 0, 'R');
                $pdf->Cell(30, 6, $item['unitPrice'], 1, 0, 'R');
                $pdf->Cell(38, 6, $item['total'], 1, 1, 'R');
            }
        }

        $pdf->Ln(8);

        // Totals
        $pdf->SetFont('helvetica', '', 10);
        $x = 110;
        $pdf->SetX($x);
        $pdf->Cell(40, 7, 'Net Total:', 0, 0, 'R');
        $pdf->Cell(30, 7, $parser->getTaxBasisTotal() . ' ' . $currency, 0, 1, 'R');
        $pdf->SetX($x);
        $pdf->Cell(40, 7, 'Tax:', 0, 0, 'R');
        $pdf->Cell(30, 7, $parser->getTaxTotal() . ' ' . $currency, 0, 1, 'R');

        $pdf->SetX($x);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(40, 8, 'Total Due:', 0, 0, 'R');
        $pdf->Cell(30, 8, $parser->getTotalAmount() . ' ' . $currency, 0, 1, 'R');

        // Footer note
        $pdf->Ln(15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 5, 'Generated by CII-to-FacturX - FacturX/ZUGFeRD compliant PDF', 0, 1, 'C');
    }
}
