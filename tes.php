<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Transaksi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .progress-container {
            width: 300px;
            background-color: #2d2d2d;
            padding: 20px;
            border-radius: 10px;
            color: white;
        }
        .progress-step {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .progress-step:last-child {
            margin-bottom: 0;
        }
        .step-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 10px;
        }
        .step-icon.completed {
            background-color: #4CAF50; /* Warna hijau untuk status selesai */
        }
        .step-icon.in-progress {
            background-color: #FF9800; /* Warna oranye untuk status pembayaran */
        }
        .step-icon.pending {
            background-color: #333; /* Warna abu untuk status menunggu */
        }
        .step-text {
            font-size: 16px;
        }
        .step-text .subtext {
            font-size: 12px;
            color: #bbb;
        }
    </style>
</head>
<body>
    <div class="progress-container">
        <!-- Transaksi Dibuat -->
        <div class="progress-step">
            <div class="step-icon completed">
                <span>✔</span>
            </div>
            <div class="step-text">
                Transaksi Dibuat
                <div class="subtext">Transaksi berhasil dibuat.</div>
            </div>
        </div>

        <!-- Pembayaran -->
        <div class="progress-step">
            <div class="step-icon in-progress">
                <span>●</span>
            </div>
            <div class="step-text">
                Pembayaran
                <div class="subtext">Silahkan melakukan pembayaran.</div>
            </div>
        </div>

        <!-- Transaksi Selesai -->
        <div class="progress-step">
            <div class="step-icon pending">
                <span></span>
            </div>
            <div class="step-text">
                Selesai
                <div class="subtext">Transaksi selesai.</div>
            </div>
        </div>
    </div>
</body>
</html>
