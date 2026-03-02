<?php

namespace CiiToFacturX;

/**
 * Parses a CII (Cross-Industry Invoice) XML document and extracts invoice data.
 */
class CiiParser
{
    const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    const NS_QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';
    const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    /** @var \SimpleXMLElement */
    private $xml;

    /** @var string Raw XML content */
    private $rawXml;

    /**
     * @param string $xmlContent Raw XML string
     * @throws \InvalidArgumentException if XML is not valid CII
     */
    public function __construct($xmlContent)
    {
        $this->rawXml = $xmlContent;

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new \InvalidArgumentException('Invalid XML: ' . trim($msg));
        }

        // Check root element
        $rootName = $xml->getName();
        if ($rootName !== 'CrossIndustryInvoice') {
            throw new \InvalidArgumentException(
                'Not a CII document. Expected root element "CrossIndustryInvoice", got "' . $rootName . '"'
            );
        }

        $xml->registerXPathNamespace('rsm', self::NS_RSM);
        $xml->registerXPathNamespace('ram', self::NS_RAM);
        $xml->registerXPathNamespace('qdt', self::NS_QDT);
        $xml->registerXPathNamespace('udt', self::NS_UDT);

        $this->xml = $xml;
    }

    /**
     * @return string
     */
    public function getRawXml()
    {
        return $this->rawXml;
    }

    /**
     * Detect the FacturX profile from the XML context.
     *
     * @return string Profile name (e.g. "MINIMUM", "EN 16931", "EXTENDED")
     */
    public function getProfile()
    {
        $nodes = $this->xml->xpath(
            '//rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'
        );
        if (empty($nodes)) {
            return 'UNKNOWN';
        }

        $guidelineId = (string)$nodes[0];

        $profiles = [
            'minimum'   => 'MINIMUM',
            'basicwl'   => 'BASIC WL',
            'basic'     => 'BASIC',
            'en16931'   => 'EN 16931',
            'extended'  => 'EXTENDED',
        ];

        $guidelineLower = strtolower($guidelineId);
        foreach ($profiles as $key => $name) {
            if (strpos($guidelineLower, $key) !== false) {
                return $name;
            }
        }

        return 'UNKNOWN';
    }

    /**
     * @return string Invoice number
     */
    public function getInvoiceNumber()
    {
        $nodes = $this->xml->xpath('//rsm:ExchangedDocument/ram:ID');
        return !empty($nodes) ? (string)$nodes[0] : '';
    }

    /**
     * @return string Invoice type code
     */
    public function getTypeCode()
    {
        $nodes = $this->xml->xpath('//rsm:ExchangedDocument/ram:TypeCode');
        return !empty($nodes) ? (string)$nodes[0] : '';
    }

    /**
     * @return string Formatted date (YYYY-MM-DD)
     */
    public function getIssueDate()
    {
        $nodes = $this->xml->xpath('//rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString');
        if (empty($nodes)) {
            return '';
        }
        $dateStr = (string)$nodes[0];
        // CII dates are typically YYYYMMDD (format 102)
        if (strlen($dateStr) === 8 && ctype_digit($dateStr)) {
            return substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
        }
        return $dateStr;
    }

    /**
     * @return array{name: string, address: string, country: string, vat: string}
     */
    public function getSeller()
    {
        return $this->getTradeParty(
            '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty'
        );
    }

    /**
     * @return array{name: string, address: string, country: string, vat: string}
     */
    public function getBuyer()
    {
        return $this->getTradeParty(
            '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty'
        );
    }

    /**
     * @return string Currency code (e.g. "EUR")
     */
    public function getCurrency()
    {
        $nodes = $this->xml->xpath(
            '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode'
        );
        return !empty($nodes) ? (string)$nodes[0] : 'EUR';
    }

    /**
     * @return string Total amount due
     */
    public function getTotalAmount()
    {
        $nodes = $this->xml->xpath(
            '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement'
            . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:DuePayableAmount'
        );
        return !empty($nodes) ? (string)$nodes[0] : '0.00';
    }

    /**
     * @return string Tax basis total amount
     */
    public function getTaxBasisTotal()
    {
        $nodes = $this->xml->xpath(
            '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement'
            . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxBasisTotalAmount'
        );
        return !empty($nodes) ? (string)$nodes[0] : '0.00';
    }

    /**
     * @return string Tax total amount
     */
    public function getTaxTotal()
    {
        $nodes = $this->xml->xpath(
            '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement'
            . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount'
        );
        return !empty($nodes) ? (string)$nodes[0] : '0.00';
    }

    /**
     * @return string Grand total amount
     */
    public function getGrandTotal()
    {
        $nodes = $this->xml->xpath(
            '//rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement'
            . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount'
        );
        return !empty($nodes) ? (string)$nodes[0] : '0.00';
    }

    /**
     * @return array[] Line items with keys: number, name, quantity, unitPrice, total
     */
    public function getLineItems()
    {
        $lines = [];
        $lineNodes = $this->xml->xpath(
            '//rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem'
        );

        if (empty($lineNodes)) {
            return $lines;
        }

        foreach ($lineNodes as $lineNode) {
            $lineNode->registerXPathNamespace('ram', self::NS_RAM);

            $number = $lineNode->xpath('ram:AssociatedDocumentLineDocument/ram:LineID');
            $name = $lineNode->xpath('ram:SpecifiedTradeProduct/ram:Name');
            $quantity = $lineNode->xpath(
                'ram:SpecifiedLineTradeDelivery/ram:BilledQuantity'
            );
            $unitPrice = $lineNode->xpath(
                'ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount'
            );
            $total = $lineNode->xpath(
                'ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'
            );

            $lines[] = [
                'number'    => !empty($number) ? (string)$number[0] : '',
                'name'      => !empty($name) ? (string)$name[0] : '',
                'quantity'  => !empty($quantity) ? (string)$quantity[0] : '',
                'unitPrice' => !empty($unitPrice) ? (string)$unitPrice[0] : '',
                'total'     => !empty($total) ? (string)$total[0] : '',
            ];
        }

        return $lines;
    }

    /**
     * Extract trade party info from xpath.
     *
     * @param string $basePath XPath to the trade party element
     * @return array{name: string, address: string, country: string, vat: string}
     */
    private function getTradeParty($basePath)
    {
        $result = ['name' => '', 'address' => '', 'country' => '', 'vat' => ''];

        $nameNodes = $this->xml->xpath($basePath . '/ram:Name');
        if (!empty($nameNodes)) {
            $result['name'] = (string)$nameNodes[0];
        }

        $lineOne = $this->xml->xpath($basePath . '/ram:PostalTradeAddress/ram:LineOne');
        $postcode = $this->xml->xpath($basePath . '/ram:PostalTradeAddress/ram:PostcodeCode');
        $city = $this->xml->xpath($basePath . '/ram:PostalTradeAddress/ram:CityName');

        $addressParts = [];
        if (!empty($lineOne)) {
            $addressParts[] = (string)$lineOne[0];
        }
        if (!empty($postcode)) {
            $addressParts[] = (string)$postcode[0];
        }
        if (!empty($city)) {
            $addressParts[] = (string)$city[0];
        }
        $result['address'] = implode(', ', $addressParts);

        $country = $this->xml->xpath($basePath . '/ram:PostalTradeAddress/ram:CountryID');
        if (!empty($country)) {
            $result['country'] = (string)$country[0];
        }

        $vat = $this->xml->xpath($basePath . '/ram:SpecifiedTaxRegistration/ram:ID');
        if (!empty($vat)) {
            $result['vat'] = (string)$vat[0];
        }

        return $result;
    }
}
