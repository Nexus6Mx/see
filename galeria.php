<?php
/**
 * Customer Gallery - Public View
 * Displays evidence photos and videos for a specific order using secure token
 */

require_once __DIR__ . '/api/config/database.php';

// Get token from URL
$token = $_GET['t'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die('Token inválido o expirado');
}

// Connect to database
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    die('Error de conexión. Por favor intenta más tarde.');
}

// Validate token
$tokenHash = hash('sha256', $token);

$tokenQuery = "
    SELECT * FROM galeria_tokens 
    WHERE token_hash = :token_hash 
    AND expira_en > NOW()
    LIMIT 1
";
$tokenStmt = $db->prepare($tokenQuery);
$tokenStmt->bindParam(':token_hash', $tokenHash);
$tokenStmt->execute();
$tokenData = $tokenStmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    http_response_code(404);
    die('Este enlace ha expirado o no es válido.');
}

$ordenNumero = $tokenData['orden_numero'];

// Update view count
$updateQuery = "
    UPDATE galeria_tokens 
    SET vistas_count = vistas_count + 1,
        ultima_vista_ip = :ip,
        ultima_vista_fecha = NOW()
    WHERE id = :id
";
$updateStmt = $db->prepare($updateQuery);
$updateStmt->execute([
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ':id' => $tokenData['id']
]);

// Get evidences
$evidenciasQuery = "
    SELECT * FROM evidencias 
    WHERE orden_numero = :orden_numero 
    AND estado = 'activo'
    ORDER BY fecha_creacion ASC
";
$evidenciasStmt = $db->prepare($evidenciasQuery);
$evidenciasStmt->bindParam(':orden_numero', $ordenNumero);
$evidenciasStmt->execute();
$evidencias = $evidenciasStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($evidencias)) {
    http_response_code(404);
    die('No se encontraron evidencias para esta orden.');
}

// Load R2 config for CDN URL
$r2Config = require __DIR__ . '/api/config/r2_config.php';
$cdnUrl = rtrim($r2Config['cdn_url'], '/');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evidencias - Orden #
        <?php echo htmlspecialchars($ordenNumero); ?>
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .stat {
            background: #f0f0f0;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            color: #555;
        }

        .stat strong {
            color: #667eea;
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .evidence-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .evidence-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.25);
        }

        .evidence-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }

        .evidence-info {
            padding: 15px;
        }

        .evidence-type {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .evidence-date {
            color: #999;
            font-size: 13px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            padding: 20px;
        }

        .modal-content {
            position: relative;
            margin: auto;
            max-width: 90%;
            max-height: 90%;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .modal img,
        .modal video {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
        }

        .modal-close:hover {
            color: #667eea;
        }

        .footer {
            text-align: center;
            color: white;
            margin-top: 30px;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 22px;
            }

            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            .evidence-thumbnail {
                height: 150px;
            }

            .modal-close {
                right: 20px;
            }
        }

        .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: rgba(102, 126, 234, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .play-icon::after {
            content: '';
            width: 0;
            height: 0;
            border-left: 20px solid white;
            border-top: 12px solid transparent;
            border-bottom: 12px solid transparent;
            margin-left: 5px;
        }

        .evidence-card-wrapper {
            position: relative;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📸 Evidencias del Servicio</h1>
            <p>Orden #
                <?php echo htmlspecialchars($ordenNumero); ?>
            </p>
            <div class="stats">
                <div class="stat">
                    <strong>
                        <?php echo count($evidencias); ?>
                    </strong> archivos
                </div>
                <div class="stat">
                    Válido hasta: <strong>
                        <?php echo date('d/m/Y', strtotime($tokenData['expira_en'])); ?>
                    </strong>
                </div>
            </div>
        </div>

        <div class="gallery">
            <?php foreach ($evidencias as $evidencia): ?>
                <?php
                $thumbnailUrl = $evidencia['thumbnail_path']
                    ? $cdnUrl . '/' . ltrim($evidencia['thumbnail_path'], '/')
                    : $cdnUrl . '/' . ltrim($evidencia['archivo_path'], '/');
                $fullUrl = $cdnUrl . '/' . ltrim($evidencia['archivo_path'], '/');
                $isVideo = $evidencia['archivo_tipo'] === 'video';
                ?>
                <div class="evidence-card"
                    onclick="openModal('<?php echo htmlspecialchars($fullUrl); ?>', '<?php echo $isVideo ? 'video' : 'image'; ?>')">
                    <div class="evidence-card-wrapper">
                        <img src="<?php echo htmlspecialchars($thumbnailUrl); ?>" alt="Evidencia" class="evidence-thumbnail"
                            loading="lazy">
                        <?php if ($isVideo): ?>
                            <div class="play-icon"></div>
                        <?php endif; ?>
                    </div>
                    <div class="evidence-info">
                        <span class="evidence-type">
                            <?php echo $isVideo ? '🎥 Video' : '📷 Foto'; ?>
                        </span>
                        <div class="evidence-date">
                            <?php echo date('d/m/Y H:i', strtotime($evidencia['fecha_creacion'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <p>ERR Automotriz - Sistema de Evidencias</p>
            <p style="font-size: 12px; margin-top: 5px;">Este enlace es privado y personal. No lo compartas.</p>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal" onclick="closeModal()">
        <span class="modal-close">&times;</span>
        <div class="modal-content" id="modal-content"></div>
    </div>

    <script>
        function openModal(url, type) {
            const modal = document.getElementById('modal');
            const modalContent = document.getElementById('modal-content');

            modalContent.innerHTML = '';

            if (type === 'video') {
                const video = document.createElement('video');
                video.src = url;
                video.controls = true;
                video.autoplay = true;
                modalContent.appendChild(video);
            } else {
                const img = document.createElement('img');
                img.src = url;
                modalContent.appendChild(img);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('modal');
            const modalContent = document.getElementById('modal-content');

            // Stop video if playing
            const video = modalContent.querySelector('video');
            if (video) {
                video.pause();
            }

            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>

</html>