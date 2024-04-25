<?php
// Hide all errors
error_reporting(0);

// Adresse IP de l'imprimante SNMP
$ip = isset($_GET['ip']) ? $_GET['ip'] : '0';

// Communauté SNMP
$community = 'public';

// OIDs SNMP pour les informations de l'imprimante
$oids = [
    'nom' => '.1.3.6.1.2.1.1.5.0',
    'modele' => '.1.3.6.1.2.1.25.3.2.1.3.1',
    'noir' => '.1.3.6.1.2.1.43.11.1.1.9.1.1',
    'modele_noir' => '.1.3.6.1.2.1.43.11.1.1.6.1.1', // OID pour le modèle de la cartouche noire
    'cyan' => '.1.3.6.1.2.1.43.11.1.1.9.1.2',
    'modele_cyan' => '.1.3.6.1.2.1.43.11.1.1.6.1.2', // OID pour le modèle de la cartouche cyan
    'magenta' => '.1.3.6.1.2.1.43.11.1.1.9.1.3',
    'modele_magenta' => '.1.3.6.1.2.1.43.11.1.1.6.1.3', // OID pour le modèle de la cartouche magenta
    'jaune' => '.1.3.6.1.2.1.43.11.1.1.9.1.4',
    'modele_jaune' => '.1.3.6.1.2.1.43.11.1.1.6.1.4' // OID pour le modèle de la cartouche jaune
];

// Fonction pour récupérer les informations via SNMP
function getSnmpData($ip, $community, $oid)
{
    try {
        if ($ip == '0') {
            return '-';
        }

        $result = @snmpget($ip, $community, $oid);
        if ($result === false) {
            throw new Exception('No such OID');
        }
        if (preg_match('/INTEGER: (\d+)/', $result, $matches)) {
            return $matches[1];  // Retourne uniquement la partie numérique pour les toners
        } else if (preg_match('/STRING: "(.*)"/', $result, $matches)) {
            return $matches[1];  // Retourne la partie chaîne de caractères pour le nom et le modèle
        } else if (preg_match('/Hex-STRING: (.*)/', $result, $matches)) {
            $hex = explode(' ', $result);
            $ascii = '';
            foreach ($hex as $char) {
                $ascii .= chr(hexdec($char));
            }
            // Supprimer le premier caractère de $ascii
            $ascii = substr($ascii, 1);
            return $ascii;
        }
        return '-';  // Retourne '-' si aucun format connu n'est trouvé
    } catch (Exception $e) {
        return '-';  // Retourne '-' en cas d'erreur
    }
}

// Collecte des informations
$infoImprimante = [];
foreach ($oids as $key => $oid) {
    $infoImprimante[$key] = getSnmpData($ip, $community, $oid);
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>État de l'imprimante</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .progress-bar {
            transition: width 0.8s ease-in-out;
            /* Animation de 2 secondes pour la largeur */
        }

        /* Initial state of the progress bar */
        .progress-bar-initial {
            width: 0%;
            /* Démarre de 0% */
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach((bar, index) => {
                setTimeout(() => {
                    const targetWidth = bar.getAttribute('data-target-width');
                    bar.style.width = targetWidth; // Déclenche l'animation avec un délai
                    bar.classList.remove('progress-bar-initial'); // Enlève l'état initial pour permettre l'animation
                }, index * 90); // Décalage de 500 ms par barre
            });
        });
    </script>
</head>

<body class="bg-gray-100 p-5 flex justify-center items-center min-h-screen">
    <div class="w-full max-w-lg p-5 bg-white shadow-lg rounded-lg">
        <h1 class="text-xl font-bold text-center mb-4">État de l'imprimante</h1>

        <!-- Formulaire pour entrer le nom de l'imprimante -->
        <form class="flex mb-5">
            <input type="text" name="ip" placeholder="Entrez le nom de l'imprimante" class="border p-2 rounded w-full mr-2" required>
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                GO
            </button>
        </form>

        <p>Nom: <strong><?= htmlspecialchars($infoImprimante['nom']) ?></strong></p>
        <p>Modèle: <strong><?= htmlspecialchars($infoImprimante['modele']) ?></strong></p>

        <div class="space-y-4 mt-5 mb-5">
            <?php foreach (['noir' => 'black', 'cyan' => 'blue-500', 'magenta' => 'pink-500', 'jaune' => 'yellow-500'] as $key => $color) : ?>
                <?php
                $changeCyanToGray = ($infoImprimante['magenta'] == "-" || $infoImprimante['jaune'] == "-");
                ?>
                <?php if (!($infoImprimante[$key] == "-" && ($key == 'magenta' || $key == 'jaune'))) : ?>
                    <p><?= htmlspecialchars($infoImprimante['modele_' . $key]) ?></p>
                    <div class="w-full bg-gray-200 rounded-full">
                        <div class="bg-<?= $key == 'cyan' && $changeCyanToGray ? 'gray-500' : ($infoImprimante[$key] == "-" ? 'black' : $color) ?> text-xs font-medium text-white text-center p-0.5 leading-none rounded-full progress-bar progress-bar-initial" data-target-width="<?= intval($infoImprimante[$key]) ?>%">
                            <?= intval($infoImprimante[$key]) ?>%
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="text-center"><a href="https://<?= $ip ?>" target="_blank" class="text-blue-700 hover:text-blue-900">https://<?= $ip ?></a></p>
    </div>
</body>

</html>
