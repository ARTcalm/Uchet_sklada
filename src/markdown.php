<?php
declare(strict_types=1);

function parse_markdown(string $text): string {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 1. Картинки (обрабатываем первыми, чтобы не попадали в абзацы)
    // Формат: ![alt текст](url)
    $text = preg_replace('/!\[(.*?)\]\((.*?)\)/s', '<img src="$2" alt="$1" class="article-image">', $text);

    // 2. Заголовки
    $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);

    // 3. Жирный текст
    $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);

    // 4. Списки
    $text = preg_replace('/^(?:\d+\.|\-)\s(.*?)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*?<\/li>)/s', '<ul>$1</ul>', $text);

    // 5. Таблицы
    $lines = explode("\n", $text);
    $newLines = [];
    $inTable = false;
    $tableHtml = '';

    foreach ($lines as $line) {
        $trimLine = trim($line);
        
        if (strpos($trimLine, '|') === 0) {
            if (!$inTable) {
                $tableHtml = '<table class="article-table">';
                $inTable = true;
            }
            
            if (strpos($trimLine, ':---') !== false) continue; 

            $cells = explode('|', trim($trimLine, '|'));
            $rowHtml = '<tr>';
            $isHeader = (substr_count($tableHtml, '<tr>') === 0);
            
            foreach ($cells as $cell) {
                $tag = $isHeader ? 'th' : 'td';
                $rowHtml .= "<{$tag}>" . trim($cell) . "</{$tag}>";
            }
            $rowHtml .= '</tr>';
            $tableHtml .= $rowHtml;
            
        } else {
            if ($inTable) {
                $tableHtml .= '</table>';
                $newLines[] = $tableHtml;
                $inTable = false;
            }
            $newLines[] = $line;
        }
    }
    
    if ($inTable) {
        $tableHtml .= '</table>';
        $newLines[] = $tableHtml;
    }
    
    $text = implode("\n", $newLines);

    // 6. Абзацы (исключаем уже готовые теги)
    $text = preg_replace('/^(?!<[h1-6uolt]|<img|<table|<tr|<div)(.*?)$/m', '<p>$1</p>', $text);
    
    // 7. Чистим пустые параграфы
    $text = str_replace(['<p></p>', '<p><h', '</h2></p>', '<p><ul', '</ul></p>', '<p><table', '</table></p>', '<p><img', 'class="article-image"></p>'], 
                        ['', '<h', '</h2>', '<ul', '</ul>', '<table', '</table>', '', 'class="article-image">'], 
                        $text);

    return $text;
}