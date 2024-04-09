<?php

namespace Done\Subtitles\Code\Other;

class DocxToText
{
    // text might not be correctly ordered
    public static function text($path): string
    {
        $that = new self($path);

        $content = '';
//        $content .= $that->getAllHeadersXml();
        $content .= $that->getFileContent('word/document.xml');
//        $content .= $that->getFileContent('word/footnotes.xml');
//        $content .= $that->getAllFootersXml();


//        $content = str_replace('</w:r></w:p></w:tc><w:tc>', " ", $content); // original code poster had this
        $content = str_replace('<w:tab/>', "    ", $content); // tab
        $content = str_replace('<w:pStyle w:val="ListParagraph"/>', '1. ', $content); // numbering but not correct, jus for word count
        $content = preg_replace('/<w:drawing>.*<\/w:drawing>/Um', '', $content);
        $content = preg_replace('/<w:instrText.*<\/w:instrText>/Um', '', $content);
        $content = str_replace('</w:r></w:p>', "\r\n", $content);
        $striped_content = strip_tags($content);
        $striped_content = html_entity_decode($striped_content, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');

        return $striped_content;
    }

    private \ZipArchive $zip;

    private function __construct($file_content)
    {
        $tmp_file = tempnam(sys_get_temp_dir(), 'prefix_');
        file_put_contents($tmp_file, $file_content);

        $zip = new \ZipArchive();
        $opened = $zip->open($tmp_file, \ZipArchive::RDONLY); // zip archive can only open real file
        if ($opened !== true) {
            throw new \Exception();
        }
        $this->zip = $zip;
        $this->tmp_path = $tmp_file;
    }

    public function __destruct()
    {
        $this->zip->close();
        unlink($this->tmp_path);
    }

    private function pageCount(): int
    {
        $content = $this->getFileContent('docProps/app.xml');
        if ($content === '') {
            return 1; // file docProps/app.xml doesn't exist, guess that we have 1 page
        }
        $xml = new \SimpleXMLElement($content);
        return (int)$xml->Pages;
    }

    private function getFileContent($internal_path): string
    {
        return $this->zip->getFromName($internal_path);
    }

    private function getAllHeadersXml(): string
    {
        $string = $this->getFileContent('word/document.xml');
        preg_match_all('/<w:headerReference w:type="(?<type>.+)" r:id="(?<id>.+)"\/>/U', $string, $matches, PREG_SET_ORDER);
        $headers = [];
        foreach ($matches as $match) {
            $internal_filename = $this->idToFileName($match['id']);
            $headers[$match['type']] = $this->getFileContent($internal_filename);
        }

        $page_count = $this->pageCount();
        $pages = [];
        for ($i = 1; $i <= $page_count; $i++) {
            $pages[$i] = '';
        }

        if (isset($headers['default'])) {
            foreach ($pages as $key => &$page) {
                $page = $headers['default'];
            }
        }
        if (isset($headers['even'])) {
            foreach ($pages as $key => &$page) {
                if ($key % 2 === 0) {
                    $page = $headers['even'];
                }
            }
        }
        if (isset($headers['odd'])) {
            foreach ($pages as $key => &$page) {
                if ($key % 2 === 1) {
                    $page = $headers['odd'];
                }
            }
        }
        if (isset($headers['first'])) {
            $pages[1] = $headers['first'];
        }

        return implode("\n", $pages);
    }

    private function getAllFootersXml(): string
    {
        $string = $this->getFileContent('word/document.xml');
        preg_match_all('/<w:footerReference w:type="(?<type>.+)" r:id="(?<id>.+)"\/>/U', $string, $matches, PREG_SET_ORDER);
        $footers = [];
        foreach ($matches as $match) {
            $internal_filename = $this->idToFileName($match['id']);
            $footers[$match['type']] = $this->getFileContent($internal_filename);
        }

        $page_count = $this->pageCount();
        $pages = [];
        for ($i = 1; $i <= $page_count; $i++) {
            $pages[$i] = '';
        }

        if (isset($footers['default'])) {
            foreach ($pages as $key => &$page) {
                $page = $footers['default'];
            }
        }
        if (isset($footers['even'])) {
            foreach ($pages as $key => &$page) {
                if ($key % 2 === 0) {
                    $page = $footers['even'];
                }
            }
        }
        if (isset($footers['odd'])) {
            foreach ($pages as $key => &$page) {
                if ($key % 2 === 1) {
                    $page = $footers['odd'];
                }
            }
        }
        if (isset($footers['first'])) {
            $pages[1] = $footers['first'];
        }

        $xml = implode("\n", $pages);

        // remove page numbers from the footer
        $xml = preg_replace('/<w:fldChar w:fldCharType="begin">.*w:fldCharType="end".*<\/w:r>/Um', '', $xml);

        return $xml;
    }

    private function idToFileName(string $xml_id): string
    {
        $content = $this->getFileContent('word/_rels/document.xml.rels');
        preg_match_all('/Relationship.+Id="(?<id>.+)".+Target="(?<filename>.+)"/U', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match['id'] === $xml_id) {
                return 'word/' . $match['filename'];
            }
        }
        throw new \Exception("Can't find: " . $xml_id);
    }
}