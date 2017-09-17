<?php

namespace Tests\Unit;

use App\Subtitles\PlainText\WebVttCue;
use Tests\TestCase;

class WebVttCueTest extends TestCase
{
    private function assert_Valid_TimingString($timingString)
    {
        $this->assertTrue(WebVttCue::isTimingString($timingString), "'{$timingString}' is not a valid timing string");
    }

    private function assert_Invalid_TimingString($timingString)
    {
        $this->assertFalse(WebVttCue::isTimingString($timingString), "'{$timingString}' is detected as a valid timing string, it should not be");
    }

    /** @test */
    function it_identifies_valid_timing_strings()
    {
        // The timestamps must be in one of two formats:
        //   mm:ss.ttt
        //   hh:mm:ss.ttt  (hh must be at least two digits, but can be greater than two (e.g. 9999:00:00.000)

        $this->assert_Valid_TimingString('00:01.000 --> 00:04.000');
        $this->assert_Valid_TimingString('00:30.739 --> 00:00:34.074');

        $this->assert_Valid_TimingString('00:01:14.815 --> 00:01:18.114');
        $this->assert_Valid_TimingString('00:01:14.815 --> 00:01:18.114 ');
        $this->assert_Valid_TimingString(' 00:01:14.815 --> 00:01:18.114');

        $this->assert_Valid_TimingString('9999:00:00.000 --> 10000:00:00.000');


        $this->assert_Valid_TimingString('00:00:05.000 --> 00:00:10.000 line:0 position:20% size:60% align:start');
        $this->assert_Valid_TimingString('00:00:05.000 --> 00:00:10.000 line:63% position:72% align:start');
        $this->assert_Valid_TimingString('00:00:05.000 --> 00:00:10.000 line:0 position:20% size:60% align:start');
        $this->assert_Valid_TimingString('00:00:05.000 --> 00:00:10.000 vertical:rt line:-1 align:end');

        // Specs say there should be 'at least one' space before and after the arrow
        $this->assert_Valid_TimingString('00:01:14.815    --> 00:01:18.114');
        $this->assert_Valid_TimingString('00:01:14.815 -->     00:01:18.114');
    }

    /** @test */
    function it_accepts_timing_with_possible_mistakes()
    {
        // commas instead of dots
        $this->assert_Valid_TimingString('00:01,000 --> 00:04,000');
    }

    /** @test */
    function it_corrects_valid_timing_strings_with_common_mistakes()
    {
        // dot instead of comma
        $cue = (new WebVttCue())->setTimingFromString('00:00:00,000 --> 00:00:00,000');
        $this->assertSame('00:00:00.000 --> 00:00:00.000', $cue->getTimingString());
    }

    /** @test */
    function it_rejects_invalid_timing_strings()
    {
        // hh must be at least two digits
        $this->assert_Invalid_TimingString('00:30.739 --> 0:00:34.074');

        // ends before it starts
        $this->assert_Invalid_TimingString('00:00:00,001 --> 00:00:00,000');

        // no space between timing and style
        $this->assert_Invalid_TimingString('00:00:5.000 --> 00:00:10.000line:63% position:72% align:start');

        // style cant contain an arrow
        $this->assert_Invalid_TimingString('00:00:5.000 --> 00:00:10.000line:63% position:72%-->align:start');

        // seconds should be two digits
        $this->assert_Invalid_TimingString('00:00:5.000 --> 00:00:10.000');

        $this->assert_Invalid_TimingString('This man is out of ideas.');
        $this->assert_Invalid_TimingString('');
        $this->assert_Invalid_TimingString(null);
        $this->assert_Invalid_TimingString(false);
    }

    /** @test */
    function it_makes_timing_strings()
    {
        $cue = new WebVttCue();

        $cue->setTiming(0, 1000);

        $this->assertSame("00:00:00.000 --> 00:00:01.000", $cue->getTimingString());
    }

    /** @test */
    function it_preserves_valid_timing_strings_that_have_hours()
    {
        $valuesShouldNotChange = [
            '00:00:05.000 --> 00:00:10.000 line:0 position:20% size:60% align:start',
            '00:01:14.815 --> 00:01:18.114',
            '324:01:14.815 --> 9645:01:18.114',
        ];

        foreach($valuesShouldNotChange as $val) {
            $this->assertSame($val, (new WebVttCue())->setTimingFromString($val)->getTimingString());
        }
    }

    /** @test */
    function it_does_not_preserve_timing_strings_without_hours()
    {
        $cue = (new WebVttCue())->setTimingFromString('00:01.000 --> 00:04.000');

        $this->assertSame('00:00:01.000 --> 00:00:04.000', $cue->getTimingString());
    }

    /** @test */
    function timecodes_do_not_exceed_minimum_value()
    {
        $cue = new WebVttCue();

        $cue->shift("-9999999999999999999999999");

        $this->assertSame("00:00:00.000 --> 00:00:00.000", $cue->getTimingString());
    }
}