<?php

namespace App\Subtitles\PlainText;

use App\Subtitles\ContainsGenericCues;
use App\Subtitles\LoadsGenericSubtitles;
use App\Subtitles\TextFile;
use App\Subtitles\TransformsToGenericSubtitle;
use App\Subtitles\WithFileLines;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Srt extends TextFile implements LoadsGenericSubtitles
{
    use WithFileLines, ContainsGenericCues;

    protected $extension = "srt";

    /**
     * @var SrtCue[]
     */
    protected $cues = [];

    public function __construct($source = null)
    {
        if($source === null) {
            return;
        }
        else if($source instanceof TransformsToGenericSubtitle) {
            $this->loadGenericSubtitle($source->toGenericSubtitle());
        }
        else {
            $this->loadFile($source);
        }
    }

    public function addCue($cue)
    {
        if($cue instanceof SrtCue) {
            $this->cues[] = $cue;
        }
        else if($cue instanceof GenericSubtitleCue) {
            $this->cues[] = new SrtCue($cue);
        }
        else {
            throw new \InvalidArgumentException("Invalid cue");
        }

        return $this;
    }

    public function getContentLines()
    {
        usort($this->cues, function(SrtCue $a, SrtCue $b) {
            return $a->getStartMs() <=> $b->getStartMs();
        });

        $id = 1;
        $lines = [];

        foreach($this->cues as $cue) {
            $lines[] = $id++;

            $lines = array_merge($lines, $cue->toArray());
        }

        return $lines;
    }

    public static function isThisFormat($file)
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $lines = app('TextFileReader')->getLines($filePath);

        // todo: make matching more strict by also checking for an id on the previous line (?)

        foreach($lines as $line) {
            if(SrtCue::isTimingString($line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $file string|UploadedFile A file path or UploadedFile
     * @return $this
     */
    public function loadFile($file)
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file;

        $this->originalFileNameWithoutExtension = pathinfo($name, PATHINFO_FILENAME);

        $this->filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $lines = app('TextFileReader')->getLines($this->filePath);

        $this->cues = [];

        // ensure parsing works properly on files missing the required trailing empty line
        $lines[] = "";

        $timingIndexes = [];

        for($i = 0; $i < count($lines); $i++) {
            if(SrtCue::isTimingString($lines[$i])) {
                $timingIndexes[] = $i;
            }
        }

        $timingIndexes[] = count($lines);

        for($timingIndex = 0; $timingIndex < count($timingIndexes) - 1; $timingIndex++) {
            $newCue = new SrtCue();

            $newCue->setTimingFromString($lines[$timingIndexes[$timingIndex]]);

            for($lineIndex = $timingIndexes[$timingIndex] + 1; $lineIndex < $timingIndexes[$timingIndex+1] - 1; $lineIndex++) {
                $newCue->addLine($lines[$lineIndex]);
            }

            $this->AddCue($newCue);
        }

        return $this;
    }

    public function loadGenericSubtitle(GenericSubtitle $genericSubtitle)
    {
        $this->setFilePath($genericSubtitle->getFilePath());

        $this->setFileNameWithoutExtension($genericSubtitle->getFileNameWithoutExtension());

        foreach($genericSubtitle->getCues() as $genericCue) {
            $this->addCue($genericCue);
        }

        return $this;
    }
}