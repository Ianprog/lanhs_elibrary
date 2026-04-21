<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
// get_book_content.php — AJAX: returns text content for in-browser reader
header('Content-Type: application/json');
requireLogin();

$bookId = (int)($_GET['id'] ?? 0);
if (!$bookId) { echo json_encode(['error' => 'Invalid ID.']); exit; }

$book = DB::row("SELECT id, title, author, description, subject FROM books WHERE id = ?", [$bookId]);
if (!$book) { echo json_encode(['error' => 'Book not found.']); exit; }

logActivity(currentUser()['id'], 'read_book', $book['title']);

// Build rich content from description + sample chapter text per subject
$desc = $book['description'] ?? '';

// Sample chapter content per subject so readers see real text
$sampleContent = [
    'Mathematics' => "Chapter 1: Introduction to Algebra\n\nAlgebra is a branch of mathematics that uses symbols and letters to represent numbers and quantities in formulas and equations. Understanding algebra is essential for higher mathematics and daily problem solving.\n\nChapter 2: Variables and Expressions\n\nA variable is a symbol (usually a letter) used to represent an unknown value. For example, in the expression 3x + 5, x is a variable. We combine variables and numbers with operations to form algebraic expressions.\n\nChapter 3: Solving Equations\n\nAn equation states that two expressions are equal. To solve an equation means to find the value of the variable that makes the equation true. We use inverse operations to isolate the variable on one side.",

    'Science' => "Chapter 1: The Cell — Basic Unit of Life\n\nAll living organisms are made of cells. The cell theory states: (1) All organisms are made of cells, (2) The cell is the basic unit of structure and function, (3) All cells come from pre-existing cells.\n\nChapter 2: Photosynthesis\n\nPhotosynthesis is the process by which plants use sunlight, water, and carbon dioxide to produce oxygen and energy (glucose). The equation is: 6CO₂ + 6H₂O + light energy → C₆H₁₂O₆ + 6O₂.\n\nChapter 3: Heredity and Genetics\n\nGenetics is the study of how traits are passed from parents to offspring. Gregor Mendel, called the Father of Genetics, discovered the laws of inheritance through his famous pea plant experiments.",

    'History' => "Kabanata 1: Sinaunang Pilipinas\n\nBago dumating ang mga Espanyol, ang Pilipinas ay may sariling kultura at pamumuno. Ang mga sinaunang Pilipino ay nagtatag ng mga barangay — mga komunidad na pinamumunuan ng isang datu o rajah.\n\nKabanata 2: Panahon ng Kolonyalismo\n\nNoong 1565, sinimulan ni Miguel Lopez de Legazpi ang kolonyalisasyon ng Pilipinas para sa Espanya. Itinayo niya ang unang permanenteng pamayanan sa Cebu, at noong 1571, inilipat ang kabisera sa Maynila.\n\nKabanata 3: Paglaban ng mga Pilipino\n\nHindi tumanggap nang walang paglaban ang mga Pilipino. Si Lapu-Lapu ang naging simbolo ng paglaban nang kaniyang patayin si Ferdinand Magellan noong 1521 sa Labanan ng Mactan.",

    'Literature' => "Chapter 1: Philippine Oral Literature\n\nBefore written literature arrived, Filipinos had a rich tradition of oral literature. This includes epics (tulang-epiko), myths (alamat), folktales (kwentong-bayan), riddles (bugtong), and proverbs (salawikain).\n\nChapter 2: The Baybayin Script\n\nThe Baybayin is an ancient Philippine script used before Spanish colonization. It is an abugida — a writing system where each character represents a consonant-vowel combination. Many pre-colonial documents were written in this script.\n\nChapter 3: José Rizal and Philippine Literature\n\nJosé Rizal is the Philippines' national hero and greatest writer. His novels Noli Me Tangere (1887) and El Filibusterismo (1891) exposed the abuses of Spanish colonial rule and inspired the Philippine Revolution.",

    'Technology' => "Chapter 1: What is Information and Communications Technology?\n\nInformation and Communications Technology (ICT) refers to all technologies used to handle telecommunications, broadcast media, audiovisual processing, and transmission systems. ICT has transformed every aspect of modern life.\n\nChapter 2: The Internet and World Wide Web\n\nThe Internet is a global network of interconnected computers. The World Wide Web (WWW), invented by Tim Berners-Lee in 1989, is a system of interlinked documents accessed via the Internet using a browser. The Web and the Internet are not the same thing — the Web runs on top of the Internet.\n\nChapter 3: Digital Citizenship\n\nBeing a good digital citizen means using technology responsibly and ethically. This includes protecting your personal information, respecting others online, avoiding cyberbullying, and thinking critically about information you find on the internet.",

    'Filipino' => "Aralin 1: Ang Wika ng Pilipinas\n\nAng Filipino ay ang pambansang wika ng Pilipinas. Ito ay base sa Tagalog ngunit may mga salitang hiniram mula sa iba't ibang wika tulad ng Espanyol, Ingles, at iba pang katutubong wika ng Pilipinas.\n\nAralin 2: Pangngalan at Panghalip\n\nAng pangngalan ay tumutukoy sa tao, lugar, bagay, o ideya. Halimbawa: estudyante, Maynila, aklat, kalayaan. Ang panghalip naman ay pumapalit sa pangngalan upang maiwasan ang paulit-ulit na paggamit nito. Halimbawa: ako, ikaw, siya, kami, tayo.\n\nAralin 3: Pandiwa at Pang-uri\n\nAng pandiwa ay nagpapahayag ng kilos o estado. Halimbawa: kumain, naglaro, mag-aaral. Ang pang-uri naman ay naglalarawan sa pangngalan o panghalip. Halimbawa: maganda, matalino, masipag.",
];

$subjectContent = $sampleContent[$book['subject']] ?? $sampleContent['Science'];

// Combine: show description as intro, then sample chapter content
$fullContent = '';
if ($desc) {
    $fullContent .= "About This Book\n\n" . $desc . "\n\n---\n\n";
}
$fullContent .= $subjectContent;

echo json_encode([
    'success' => true,
    'title'   => $book['title'],
    'author'  => $book['author'],
    'content' => $fullContent,
]);
