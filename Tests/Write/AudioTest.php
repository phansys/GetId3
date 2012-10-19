<?php

namespace GetId3\Tests\Write;

use GetId3\GetId3Core;
use GetId3\Write\Tags;

class AudioTest extends \PHPUnit_Framework_TestCase
{
    protected static $mp3File;
    protected static $class;
    protected static $classWriter;

    protected function setUp()
    {
        self::$mp3File = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'mp3demo.mp3';
        self::$class = 'GetId3\\GetId3Core';
        self::$classWriter = 'GetId3\\Write\\Tags';

        if (!class_exists(self::$class)) {
            $this->markTestSkipped(self::$class . ' is not available.');
        }
        $this->assertTrue(class_exists(self::$class));
        $rc = new \ReflectionClass(self::$class);
        $this->assertTrue($rc->hasProperty('option_md5_data') && $rc->hasProperty('option_md5_data_source') && $rc->hasProperty('encoding'));
        $this->assertTrue($rc->hasMethod('analyze'));
        $rm = new \ReflectionMethod(self::$class, 'analyze');
        $this->assertTrue($rm->isPublic());
    }

    public function testFile()
    {
        $this->assertTrue(file_exists(self::$mp3File) && is_writable(self::$mp3File) && is_readable(self::$mp3File));
    }

    public function testWriteId3Tags()
    {        
        if (!class_exists(self::$classWriter)) {
            $this->markTestSkipped(self::$classWriter . ' is not available.');
        }
        
        $this->assertTrue(class_exists(self::$classWriter));
        $rc = new \ReflectionClass(self::$classWriter);
        $this->assertTrue($rc->hasProperty('filename') && $rc->hasProperty('tagformats') && $rc->hasProperty('tag_encoding'));
        $this->assertTrue($rc->hasMethod('WriteTags'));
        $rm = new \ReflectionMethod(self::$classWriter, 'WriteTags');
        $this->assertTrue($rm->isPublic());

        $tagwriter = new Tags();
        $tagwriter->filename = self::$mp3File;
        $tagwriter->tagformats = array('id3v1', 'id3v2.3');
        $tagwriter->overwrite_tags = true;
        $tagwriter->tag_encoding   = 'UTF-8';
        $tagwriter->remove_other_tags = true;

        $hash = ' ' . substr(md5(mt_rand()), 0, 8);
        $TagData = array(
            'title'   => array('My Song' . $hash),
            'artist'  => array('The Artist' . $hash),
            'album'   => array('Greatest Hits' . $hash),
            'year'    => array('1986' . $hash),
            'genre'   => array('Electronic' . $hash),
            'comment' => array('excellent!' . $hash),
            'track'   => array('04/16' . $hash),
        );
        $tagwriter->tag_data = $TagData;

        $this->assertTrue($tagwriter->WriteTags());
        $this->assertTrue(empty($tagwriter->warnings));

        $getId3 = new GetId3Core();
        $getId3->option_md5_data        = true;
        $getId3->option_md5_data_source = true;
        $getId3->encoding               = 'UTF-8';
        $audio = $getId3->analyze(self::$mp3File);
        $this->assertTrue(!isset($audio['error']));
        $this->assertTrue(isset($audio['audio']['dataformat']) && $audio['audio']['dataformat'] == 'mp3');
        $this->assertTrue(array_key_exists('tags', $audio));
        $this->assertTrue(array_key_exists('id3v1', $audio['tags']) && array_key_exists('id3v2', $audio['tags']));

        $this->assertTrue($audio['tags']['id3v1']['title'][0] == $TagData['title'][0]);
        $this->assertTrue($audio['tags']['id3v2']['title'][0] == $TagData['title'][0]);   
    }        
}
