<?php

namespace Tests\Helpers;

use Done\Subtitles\Code\Helpers;
use PHPUnit\Framework\TestCase;

class RemoveHtmlTest extends TestCase
{
    public function testRemoveHtml()
    {
        $this->assertEquals('a', Helpers::removeOnlyHtmlTags('<b>a</b>'), 1);
        $this->assertEquals('a', Helpers::removeOnlyHtmlTags('<b style="something">a</b>'), 2);
        $this->assertEquals('a', Helpers::removeOnlyHtmlTags('a<br>'), 3);
        $this->assertEquals('a', Helpers::removeOnlyHtmlTags('a<br >'), 4);
        $this->assertEquals('a', Helpers::removeOnlyHtmlTags('a<br />'), 5);
        $this->assertEquals('a', Helpers::removeOnlyHtmlTags('a<br/>'), 6);
        $this->assertEquals('a b', Helpers::removeOnlyHtmlTags('a <a href="something">b</a>'), 7);
        $this->assertEquals('a <a sentence is here>', Helpers::removeOnlyHtmlTags('a <a sentence is here>'), 8);
        $this->assertEquals('a b', Helpers::removeOnlyHtmlTags('a <i> <i> b'));
        $this->assertEquals('a ', Helpers::removeOnlyHtmlTags('a </ I>'));
        $this->assertEquals(' www.url.net ', Helpers::removeOnlyHtmlTags('<font color = "# 00ffff"> www.url.net </ font> </ font>'));
        $this->assertEquals('word', Helpers::removeOnlyHtmlTags('<font COLOR="WHITE">word'));
        $this->assertEquals('<font', Helpers::removeOnlyHtmlTags('<font'));
        $this->assertEquals('<font color = ', Helpers::removeOnlyHtmlTags('<font color = '));
    }
}