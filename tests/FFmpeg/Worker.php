<?php

namespace Workerfy\Tests\FFmpeg;

class Worker extends \Workerfy\AbstractProcess
{

    protected $videoPath = '/home/wwwroot/video.mp4';


    /**
     * @inheritDoc
     */
    public function run()
    {
        $ffmpeg = \FFMpeg\FFMpeg::create();
        $video = $ffmpeg->open($this->videoPath);

//        $video->filters()
//            ->resize(new \FFMpeg\Coordinate\Dimension(320, 240))
//            ->synchronize();
//        $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(10))
//            ->save('frame1.jpg');

        $format = new \FFMpeg\Format\Video\X264();
        $format->on('progress', function ($video, $format, $percentage) {
            echo "$percentage % transcoded";
        });

        $format
            ->setKiloBitrate(1000)
            ->setAudioChannels(2)
            ->setAudioKiloBitrate(256);

        $video->save($format, 'video.avi');
    }

    public function onHandleException(\Throwable $throwable, array $context = [])
    {
        var_dump($throwable->getMessage());
    }
}

