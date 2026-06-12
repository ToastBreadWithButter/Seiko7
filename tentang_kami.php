<?php
require_once __DIR__ . '/app/bootstrap.php';?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - <?= e(APP_NAME) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .page-header {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: white;
            text-align: center;
            padding: 3rem 1.5rem 2rem;
            border-bottom: 3px solid #f59e0b;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.8;
        }

        .about-container {
            max-width: 1100px;
            margin: 3rem auto;
            padding: 0 1.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 3rem;
        }

        /* Kolom kiri: foto tim */
        .about-image {
            flex: 1 1 400px;
            display: flex;
            justify-content: center;
        }

        .about-image img {
            max-width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 20px 30px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }

        .about-image img:hover {
            transform: scale(1.02);
        }

        /* Kolom kanan: deskripsi */
        .about-content {
            flex: 1 1 400px;
        }

        .about-content h2 {
            font-size: 2rem;
            color: #0f172a;
            margin-bottom: 1rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .about-content h2::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 3px;
            background: #f59e0b;
            border-radius: 3px;
        }

        .about-content p {
            font-size: 1.1rem;
            margin-bottom: 1.25rem;
            color: #475569;
        }

        .about-content .highlight {
            background: #fef9c3;
            padding: 0.2em 0.5em;
            border-radius: 4px;
            font-weight: 600;
            color: #854d0e;
        }

        .about-stats {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #f59e0b;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #64748b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .about-container {
                flex-direction: column-reverse;
                text-align: center;
            }

            .about-content h2::after {
                left: 50%;
                transform: translateX(-50%);
            }

            .about-stats {
                justify-content: center;
            }

            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

    <header class="page-header">
        <h1>Tentang Kami</h1>
        <p>Mengenal lebih dekat tim di balik <strong><?= e(APP_NAME) ?></strong></p>
    </header>

    <main class="about-container">
        <!-- Foto Tim (Kiri) -->
        <div class="about-image">
            <!-- Ganti src dengan path gambar tim Anda -->
            <img src="assets/images/tim_kami.jpeg" alt="Foto Tim <?= e(APP_NAME) ?>">
        </div>

        <!-- Deskripsi Singkat (Kanan) -->
        <div class="about-content">
            <h2>Cerita Kami</h2>
            <p>
                <strong><?= e(APP_NAME) ?></strong> hadir untuk memberikan solusi terbaik di bidang E-commerce dan online shopping. 
                Kami percaya bahwa <span class="highlight">inovasi dan dedikasi</span> adalah kunci untuk melayani Anda dengan maksimal.
            </p>
            <p>
                Didirikan sejak 2020, tim kami terdiri dari para profesional yang berpengalaman dan selalu semangat menghadirkan produk/layanan berkualitas tinggi. Kepuasan Anda adalah prioritas utama kami.
            </p>
            <p>
                Mari tumbuh bersama dan ciptakan sesuatu yang luar biasa!
            </p>

            <!-- Statistik / Highlight Singkat -->
            <div class="about-stats">
                <div class="stat-item">
                    <div class="stat-number">5+</div>
                    <div class="stat-label">Tahun Pengalaman</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">200+</div>
                    <div class="stat-label">Klien Puas</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">10+</div>
                    <div class="stat-label">Negara</div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>