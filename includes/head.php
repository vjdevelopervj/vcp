<?php /** @var string $pageTitle */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle ?? 'vcp') ?></title>
    <meta name="description" content="vcp — AI tool untuk membuat dokumen markdown Planning, Design, dan PRD dalam hitungan detik.">
    <link rel="icon" type="image/png" sizes="64x64" href="assets/img/favicon-v2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                                  display: ['"Space Grotesk"', 'Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        ink: '#0B0B12',
                        panel: '#15151F',
                        mist: '#20202D',
                        line: '#2A2A3A',
                        paper: '#F4F5FA',
                        brand: { 300: '#CBF407', 400: '#A1F102', 500: '#86E00A', 600: '#69D202' },
                    },
                    boxShadow: {
                        soft: '0 10px 40px -12px rgba(0, 0, 0, 0.6)',
                    },
                },
            },
        };
    </script>
    <style>
        :root {
            --sat: env(safe-area-inset-top, 0px);
            --sab: env(safe-area-inset-bottom, 0px);
            --sal: env(safe-area-inset-left, 0px);
            --sar: env(safe-area-inset-right, 0px);
        }
        html, body {
            -webkit-text-size-adjust: 100%;
            -webkit-tap-highlight-color: transparent;
        }
        .blob { position: absolute; border-radius: 9999px; filter: blur(70px); pointer-events: none; }
        /* Versi layer untuk halaman yang kontennya positioned: ::after di atas jatuh
           ke atas teks dan memudarkannya, jadi grain ditaruh sebagai sibling sendiri. */
        .noise-layer {
            position: absolute; inset: 0; z-index: 0; pointer-events: none; opacity: .35; mix-blend-mode: soft-light;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }
        .noise::after {
            content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .35; mix-blend-mode: soft-light;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }
    </style>
</head>
