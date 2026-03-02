<?php
/**
 * CII-to-FacturX - Basic web interface
 *
 * Upload a CII XML (EXTENDED-CTC-FR, EN16931) and download a valid FacturX PDF.
 */

require_once __DIR__ . '/vendor/autoload.php';

use CiiToFacturX\CiiParser;
use CiiToFacturX\FacturXPdf;

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cii_file'])) {
    try {
        $file = $_FILES['cii_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload size limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload size limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded. Please select a CII XML file.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error (missing temp directory).',
                UPLOAD_ERR_CANT_WRITE => 'Server error: failed to write file.',
                UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
            ];
            throw new \RuntimeException(
                isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Upload error.'
            );
        }

        $xmlContent = file_get_contents($file['tmp_name']);
        if ($xmlContent === false || trim($xmlContent) === '') {
            throw new \RuntimeException('The uploaded file is empty.');
        }

        // Parse the CII XML
        $parser = new CiiParser($xmlContent);

        // Generate FacturX PDF
        $pdfContent = FacturXPdf::generateFromCii($parser);

        // Build download filename
        $invoiceNumber = $parser->getInvoiceNumber();
        $safeNumber = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invoiceNumber);
        $filename = 'FacturX_' . ($safeNumber ?: 'invoice') . '.pdf';

        // Send PDF as download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $pdfContent;
        exit;
    } catch (\InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (\RuntimeException $e) {
        $error = $e->getMessage();
    } catch (\Exception $e) {
        $error = 'An unexpected error occurred: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CII to FacturX</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f7fa;
            color: #333;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            padding: 40px;
            max-width: 560px;
            width: 100%;
        }
        h1 {
            font-size: 1.6rem;
            margin-bottom: 8px;
            color: #1a1a2e;
        }
        .subtitle {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 24px;
        }
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            margin-bottom: 20px;
        }
        .upload-area:hover,
        .upload-area.dragover {
            border-color: #4a90d9;
            background: #f0f7ff;
        }
        .upload-area input[type="file"] {
            display: none;
        }
        .upload-area .icon {
            font-size: 2.5rem;
            margin-bottom: 8px;
        }
        .upload-area .label {
            font-size: 1rem;
            color: #555;
        }
        .upload-area .hint {
            font-size: 0.8rem;
            color: #999;
            margin-top: 4px;
        }
        .file-name {
            display: none;
            background: #e8f4e8;
            border: 1px solid #b5d8b5;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #2d6a2d;
            word-break: break-all;
        }
        .file-name.visible {
            display: block;
        }
        .error {
            background: #fde8e8;
            border: 1px solid #f5c6c6;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
            color: #c0392b;
            font-size: 0.9rem;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #4a90d9;
            color: #fff;
        }
        .btn-primary:hover {
            background: #357abd;
        }
        .btn-primary:disabled {
            background: #a0c4e8;
            cursor: not-allowed;
        }
        .validator-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #4a90d9;
            text-decoration: none;
        }
        .validator-link:hover {
            text-decoration: underline;
        }
        .info {
            background: #e8f0fe;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #1a56db;
        }
        footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.75rem;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CII to FacturX</h1>
        <p class="subtitle">Upload a CII XML invoice to generate a valid FacturX PDF</p>

        <div class="info">
            Supported profiles: <strong>EN16931</strong>, <strong>EXTENDED</strong> (including CTC-FR).<br>
            The CII XML must be valid before conversion.
        </div>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" id="uploadArea">
                <div class="icon">📄</div>
                <div class="label">Click or drag &amp; drop your CII XML file here</div>
                <div class="hint">Accepted format: .xml</div>
                <input type="file" name="cii_file" id="ciiFile" accept=".xml,text/xml,application/xml" required>
            </div>

            <div class="file-name" id="fileName"></div>

            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                Generate FacturX PDF
            </button>
        </form>

        <a href="https://portail.fnfe-mpe.org/facturx/controle"
           target="_blank"
           rel="noopener noreferrer"
           class="validator-link">
            🔍 Validate your FacturX on FNFE-MPE validator
        </a>

        <footer>
            CII-to-FacturX &mdash; No data is stored on the server.
        </footer>
    </div>

    <script>
        (function() {
            var uploadArea = document.getElementById('uploadArea');
            var fileInput = document.getElementById('ciiFile');
            var fileNameDiv = document.getElementById('fileName');
            var submitBtn = document.getElementById('submitBtn');
            var form = document.getElementById('uploadForm');

            // Click to browse
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            // Drag and drop
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            uploadArea.addEventListener('dragleave', function() {
                uploadArea.classList.remove('dragover');
            });
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    updateFileName();
                }
            });

            // File selected
            fileInput.addEventListener('change', updateFileName);

            function updateFileName() {
                if (fileInput.files.length > 0) {
                    fileNameDiv.textContent = '✅ ' + fileInput.files[0].name;
                    fileNameDiv.classList.add('visible');
                    submitBtn.disabled = false;
                } else {
                    fileNameDiv.classList.remove('visible');
                    submitBtn.disabled = true;
                }
            }

            // Clear form after successful download
            form.addEventListener('submit', function() {
                setTimeout(function() {
                    fileInput.value = '';
                    fileNameDiv.classList.remove('visible');
                    fileNameDiv.textContent = '';
                    submitBtn.disabled = true;
                }, 1500);
            });
        })();
    </script>
</body>
</html>
