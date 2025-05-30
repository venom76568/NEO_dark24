<?php
ob_start(); // Start output buffering

require('fpdf.php');

function sanitizeFileName(string $string): string {
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $string);
}

class PDFCertificate extends FPDF {
    protected $fontPath;
    protected $name;
    protected $template;

    public function __construct($orientation, $unit, $size, $template, $fontPath, $name) {
        parent::__construct($orientation, $unit, $size);
        $this->template = $template;
        $this->fontPath = $fontPath;
        $this->name = $name;
    }

    function Header() {
        // Set background image
        $this->Image($this->template, 0, 0, $this->GetPageWidth(), $this->GetPageHeight());
    }

    function AddName() {
        $this->AddFont('Belleza','','Belleza.php'); // Font definition file must exist
        $this->SetFont('Belleza','',33); // Adjust size as needed
        $this->SetTextColor(0, 0, 0);

        // Calculate text width and position
        $textWidth = $this->GetStringWidth($this->name);
        $x = 170; // You can center it dynamically using: ($this->GetPageWidth() - $textWidth) / 2
        $y = 116; // Adjust Y position to fit your template

        $this->SetXY($x, $y);
        $this->Write(0, $this->name);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['certificate_form'])) {
    $nameInput = trim($_POST['name'] ?? '');
    $phoneInput = trim($_POST['contact'] ?? '');

    if ($nameInput === '' || $phoneInput === '') {
        echo "<h3 style='color:red; text-align:center;'>Please provide both name and phone number.</h3>";
        exit;
    }

    $found = false;
    $name = '';

    try {
        if (($handle = fopen('data.csv', 'r')) !== false) {
            // Skip header
            fgetcsv($handle, 0, ",", '"', "\\");

            while (($data = fgetcsv($handle, 0, ",", '"', "\\")) !== false) {
                if (isset($data[1]) && trim($data[1]) === $phoneInput) {
                    $found = true;
                    $name = trim($data[0]);
                    break;
                }
            }
            fclose($handle);
        } else {
            throw new RuntimeException("Failed to open data file.");
        }

        if ($found) {
            $templatePath = 'cert_template.jpg';
            $fontPath = _DIR_ . '/font/Belleza.ttf';

            if (!file_exists($templatePath)) {
                throw new RuntimeException("Certificate template image not found.");
            }

            if (!file_exists($fontPath)) {
                throw new RuntimeException("Font file not found.");
            }

            $pdf = new PDFCertificate('L', 'mm', 'A4', $templatePath, $fontPath, $name);
            $pdf->AddPage();
            $pdf->AddName();

            $cleanName = sanitizeFileName($name);
            $pdfOutput = "NEO25_Certificate_$cleanName.pdf";

            // Set proper headers for mobile download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $pdfOutput . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            $pdf->Output('I', $pdfOutput); // 'I' = inline display, 'D' = force download
        } else {
            echo "<h3 style='color:red; text-align:center;'>Phone number not found in our records. Please check and try again.</h3>";
        }
    } catch (Exception $e) {
        echo "<h3 style='color:red; text-align:center;'>Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
    }
}

ob_end_flush(); // Send output buffer
?>
