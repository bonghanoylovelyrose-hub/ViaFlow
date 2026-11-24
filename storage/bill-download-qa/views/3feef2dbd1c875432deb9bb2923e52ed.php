<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Water Bill <?php echo e($bill->prn); ?></title>
    <style>
        <?php echo $__env->make('shared.billing.statement-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        @page {
            margin: 10mm 11mm;
        }

        body {
            margin: 0;
        }

        .stmt-document {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            line-height: 1.1;
        }

        .stmt-document th,
        .stmt-document td {
            padding: 1.5pt 4pt;
        }

        .stmt-document .stmt-header {
            padding: 6pt 4pt 4pt;
            line-height: 1.25;
        }

        .stmt-document h1 {
            margin-top: 10pt;
        }

        .stmt-document .stmt-bill {
            table-layout: auto;
        }

        .stmt-document .stmt-bill,
        .stmt-document tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <?php echo $__env->make('shared.billing.statement-document', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\waterbillingsystems\resources\views/shared/billing/statement-pdf.blade.php ENDPATH**/ ?>