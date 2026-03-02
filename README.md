# CII-to-FacturX

A simple PHP web application that converts a CII (Cross-Industry Invoice) XML file into a valid **FacturX** PDF (PDF/A-3b with embedded XML).

## Features

- Upload a CII XML file (EN16931, EXTENDED / CTC-FR profiles)
- Generates a PDF/A-3b with the CII attached as `factur-x.xml`
- Downloads the resulting FacturX PDF
- Link to [FNFE-MPE validator](https://portail.fnfe-mpe.org/facturx/controle) for validation
- No storage, no database, no statistics — files are processed in memory only

## Requirements

- PHP 7.1 or higher
- [Composer](https://getcomposer.org/)

## Installation

```bash
git clone https://github.com/CyrilleGuimezanes/CII-to-facturX.git
cd CII-to-facturX
composer install
```

## Usage

### Local development

```bash
php -S localhost:8080
```

Then open [http://localhost:8080](http://localhost:8080) in your browser.

### OVH shared hosting

1. Run `composer install` locally
2. Upload all files (including `vendor/`) to your hosting
3. Point the document root to the project directory

## How It Works

1. The user uploads a valid CII XML invoice
2. The application parses the XML to extract invoice data (seller, buyer, line items, totals)
3. A PDF is generated with the invoice data rendered on it
4. The CII XML is embedded in the PDF as `factur-x.xml` with PDF/A-3b metadata
5. The resulting FacturX PDF is sent as a download

## License

MIT
