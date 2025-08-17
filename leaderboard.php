<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Archivo donde se guardan los puntajes
$scoresFile = 'scores.json';

// Función para leer puntajes
function readScores($file) {
    if (!file_exists($file)) {
        return [];
    }
    
    $content = file_get_contents($file);
    if ($content === false) {
        return [];
    }
    
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// Función para guardar puntajes
function saveScores($file, $scores) {
    // Crear directorio si no existe
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Guardar con formato bonito
    $json = json_encode($scores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Obtener leaderboard
    try {
        $scores = readScores($scoresFile);
        
        // Ordenar por puntaje descendente
        usort($scores, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Mantener solo los top 100
        $scores = array_slice($scores, 0, 100);
        
        echo json_encode([
            'success' => true,
            'leaderboard' => $scores
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al leer puntajes: ' . $e->getMessage()]);
    }
    
} elseif ($method === 'POST') {
    // Guardar nuevo puntaje
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action']) || $input['action'] !== 'save') {
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida']);
        exit;
    }
    
    // Validar y limpiar datos
    $name = trim($input['name'] ?? 'Anónimo');
    $score = intval($input['score'] ?? 0);
    $level = intval($input['level'] ?? 1);
    $date = date('Y-m-d H:i:s');
    
    // Validaciones
    if (strlen($name) > 20) {
        $name = substr($name, 0, 20);
    }
    if (empty($name)) {
        $name = 'Anónimo';
    }
    if ($score < 0) {
        $score = 0;
    }
    
    try {
        // Leer puntajes existentes
        $scores = readScores($scoresFile);
        
        // Agregar nuevo puntaje
        $newScore = [
            'name' => $name,
            'score' => $score,
            'level' => $level,
            'date' => $date
        ];
        
        $scores[] = $newScore;
        
        // Ordenar por puntaje descendente
        usort($scores, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Mantener solo los top 100
        $scores = array_slice($scores, 0, 100);
        
        // Guardar archivo actualizado
        if (saveScores($scoresFile, $scores)) {
            echo json_encode([
                'success' => true,
                'leaderboard' => $scores
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo guardar el archivo de puntajes']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al procesar puntaje: ' . $e->getMessage()]);
    }
    
} elseif ($method === 'OPTIONS') {
    // Manejar preflight CORS
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?>