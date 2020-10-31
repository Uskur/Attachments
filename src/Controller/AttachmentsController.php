<?php

namespace Uskur\Attachments\Controller;

use Uskur\Attachments\Controller\AppController;
use Gumlet\ImageResize;
use Cake\ORM\TableRegistry;
use Cake\Filesystem\File;

/**
 * Attachments Controller
 *
 * @property \Uskur\Attachments\Model\Table\AttachmentsTable $Attachments
 */
class AttachmentsController extends AppController
{

    /**
     * Add method
     *
     * @return \Cake\Http\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add($model = null, $fk = null)
    {
        $attachment = $this->Attachments->newEntity();
        if ($this->request->is('post')) {
            if (is_null($fk)) {
                $fk = $this->request->getData('fk');
            }
            if (is_null($model)) {
                $model = $this->request->getData('model');
            }
            $model = str_replace('-', '/', $model);
            $Model = TableRegistry::getTableLocator()->get($model);
            $entity = $Model->get($fk);
            if ($this->request->getData('files')) {
                foreach ($this->request->getData('files') as $file) {
                    $attachment = $this->Attachments->addUpload($entity, $file);
                }
            } else {
                $file = $this->request->getData('file');
                $attachment = $this->Attachments->addUpload($entity, $file);
            }
        }
        $this->set(compact('attachment'));
        $this->set('_serialize', ['attachment']);
        return $this->redirect($this->referer());
    }

    /**
     * Edit method
     *
     * @param string|null $id Attachment id.
     * @return \Cake\Http\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $attachment = $this->Attachments->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $attachment = $this->Attachments->patchEntity($attachment, $this->request->getData());
            if ($this->Attachments->save($attachment)) {
                $this->Flash->success(__('The attachment has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The attachment could not be saved. Please, try again.'));
            }
        }
        $articles = $this->Attachments->Articles->find('list', ['limit' => 200]);
        $this->set(compact('attachment', 'articles'));
        $this->set('_serialize', ['attachment']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Attachment id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $attachment = $this->Attachments->get($id);
        if ($this->Attachments->delete($attachment)) {
            if (!$this->request->is('ajax')) {
                $this->Flash->success(__('The attachment has been deleted.'));
            }
        } else {
            if (!$this->request->is('ajax')) {
                $this->Flash->error(__('The attachment could not be deleted. Please, try again.'));
            }
        }
        return $this->redirect($this->referer());
    }

    /**
     * w: width
     * h: height
     * c: crop
     * m: mode
     * e: enlarge
     * q: quality
     * fc: fill-color (hex, without #)
     * @param $id
     * @return \Cake\Http\Response|null
     * @throws \Gumlet\ImageResizeException
     */
    public function image($id)
    {
        //handle options
        $validOptions = ['w', 'h', 'c', 'm', 'e', 'q', 'fc'];
        $options = [];
        foreach ($validOptions as $option) {
            if ($this->request->getQuery($option)) {
                $options[$option] = $this->request->getQuery($option);
            } //default fill color to white
            elseif ($option == 'fc') {
                $options['fc'] = 'ffffff';
            } else {
                $options[$option] = null;
            }
        }

        //handle output type
        $options['type'] = IMAGETYPE_JPEG;
        //serve webp if the browser accepts
        if ($this->request->accepts('image/webp') && defined('IMAGETYPE_WEBP')) {
            $options['type'] = IMAGETYPE_WEBP;
        }

        $cacheFolder = CACHE . 'image';
        $cacheKey = implode('', array_map(
            function ($v, $k) {
                return "$k$v";
            },
            $options,
            array_keys($options)
        ));
        $cacheFile = $cacheFolder . DS . md5($id . $cacheKey);

        if (!file_exists($cacheFile)) {
            if (!file_exists($cacheFolder)) {
                mkdir($cacheFolder);
            }
            $attachment = $this->Attachments->get($id);
            //@todo show mimetype icon if not an image type
            if (!file_exists($attachment->path)) {
                throw new \Exception("File {$attachment->path} cannot be read.");
            }
            $imagePath = $attachment->path;
            //handle pdf, get first page
            if ($attachment->filetype === 'application/pdf') {
                $imagePath = "/tmp/" . uniqid();
                $imagick = new \Imagick("{$attachment->path}[0]");
                $imagick->setImageFormat('jpg');
                file_put_contents($imagePath, $imagick);
            }
            $image = new ImageResize($imagePath);
            if ($options['m'] == 'fill') {
                //resize and temporarily save
                $image->resizeToBestFit($options['w'], $options['h'], $options['e']);
                $tempImage = '/tmp/' . rand();
                $image->save($tempImage, IMAGETYPE_JPEG);

                $image = new ImageResize($imagePath);
                $image->resize($options['w'], $options['h']);
                $image->addFilter(function ($imageDesc) use ($options, $tempImage) {
                    list($r, $g, $b) = sscanf($options['fc'], "%02x%02x%02x");
                    $backgroundColor = imagecolorallocate($imageDesc, $r, $g, $b);
                    imagefilledrectangle($imageDesc, 0, 0, $options['w'], $options['h'], $backgroundColor);

                    $resizedImage = imagecreatefromjpeg($tempImage);
                    $imageHeight = imagesy($resizedImage);
                    $imageWidth = imagesx($resizedImage);
                    $destinationY = 0;
                    //position resized image
                    if ($options['h'] > $imageHeight) {
                        $destinationY = ($options['h'] - $imageHeight) / 2;
                    }
                    $destinationX = 0;
                    if ($options['w'] > $imageWidth) {
                        $destinationX = ($options['w'] - $imageWidth) / 2;
                    }
                    imagecopy($imageDesc, $resizedImage, $destinationX, $destinationY, 0, 0, $imageWidth, $imageHeight);
                    imagedestroy($resizedImage);
                    //delete temp image
                    unlink($tempImage);
                });
            } elseif ($options['w'] && $options['h'] && $options['c']) {
                $image->crop($options['w'], $options['h'], $options['e']);
            } elseif ($options['w'] && $options['h']) {
                $image->resizeToBestFit($options['w'], $options['h'], $options['e']);
            } elseif ($options['h']) {
                $image->resizeToHeight($options['h'], $options['e']);
            } elseif ($options['w']) {
                $image->resizeToWidth($options['w'], $options['e']);
            }

            //preserve PNG for transparency
            if ($attachment->filetype == 'image/png' && $options['type'] != IMAGETYPE_WEBP) {
                $options['type'] = IMAGETYPE_PNG;
            }
            $image->save($cacheFile, $options['type'], $options['q']);
        }
        if (!file_exists($cacheFile)) {
            throw new \Exception("File {$cacheFile} cannot be read.");
        }
        $file = new File($cacheFile);
        $response = $this->response->withFile($cacheFile,
            ['download' => false, 'name' => (isset($attachment) ? $attachment->filename : null)])
            ->withType($file->mime())
            ->withCache('-1 minute', '+1 month')
            ->withExpires('+1 month')
            ->withModified($file->lastChange());
        if ($response->checkNotModified($this->request)) {
            return $response;
        }

        return $response;
    }

    public function file($id, $name = null)
    {
        $attachment = $this->Attachments->get($id);
        if (!file_exists($attachment->path)) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }
        $response = $this->response->withType($attachment->filetype)
            ->withFile($attachment->path, ['download' => false, 'name' => $attachment->filename]);
        return $response;
    }

    public function download($id, $name = null)
    {
        $attachment = $this->Attachments->get($id);
        if (!file_exists($attachment->path)) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }
        $response = $this->response->withType($attachment->filetype)
            ->withFile($attachment->path, ['download' => true, 'name' => $attachment->filename]);
        return $response;
    }

    public function updatePosition($id = null, $newPosition = null)
    {
        $attachment = $this->Attachments->get($id);
        $attachment->sequence = $newPosition;
        $this->Attachments->save($attachment);
        $this->set('attachment', $attachment);
        exit();
    }

    public function reorder($fk)
    {
        $reorder = $this->Attachments->find('all',
            ['fields' => ['id'], 'conditions' => ['foreign_key' => $fk]])->order(['filename ASC'])->toArray();
        $this->Attachments->setOrder($reorder);
    }

    public function list($model, $fk)
    {
        $this->viewBuilder()->setTheme('Uskur/RemarkTemplate');
        $this->viewBuilder()->setLayout('Uskur/RemarkTemplate.topbar-pageaside-left');
        $model = str_replace('-', '/', $model);
        $attachments = $this->Attachments->find('all');
        $attachments->where(['Attachments.model' => $model, 'Attachments.foreign_key' => $fk]);
        if ($this->request->getQuery('filter') == 'image') {
            $attachments->where(['Attachments.filetype LIKE' => 'image%']);
        }

        $this->set('attachments', $attachments);
        $this->set('model', $model);
        $this->set('fk', $fk);
        $this->set('_serialize', ['attachments']);
    }

    public function fileAttribute($id)
    {
        $attachment = $this->Attachments->get($id);
        if ($this->request->is('post')) {
            foreach ($this->request->getData() as $detail => $value) {
                if ($detail == 'id') {
                    continue;
                }
                $attachment->setDetail($detail, $value);
            }
            $this->Attachments->save($attachment);
        }

        $this->set('attachment', $attachment);
        $this->set('_serialize', ['attachment']);
    }

    /**
     * @param $id
     * @param null $name
     * @throws \Exception
     * https://cakephp.blog/stream-video-with-cakephp-pt-1/
     */
    public function stream($id, $name = null)
    {
        $this->buffer = 102400;
        $attachment = $this->Attachments->get($id);
        if (!file_exists($attachment->path)) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }
        if (!($this->stream = fopen($attachment->path, 'rb'))) {
            throw new \Exception("Stream could not be opened.");
        }

        ob_get_clean();
        header("Content-Type: video/mp4");
        header("Cache-Control: max-age=311040000, public");
        header("Expires: ".gmdate('D, d M Y H:i:s', time()+311040000) . ' GMT');
        header("Last-Modified: ".gmdate('D, d M Y H:i:s', @filemtime($attachment->path)) . ' GMT' );
        $this->start = 0;
        $this->size  = filesize($attachment->path);
        $this->end   = $this->size - 1;

        header("Accept-Ranges: 0-".$this->end);
        //set header
        if (isset($_SERVER['HTTP_RANGE'])) {

            $c_start = $this->start;
            $c_end = $this->end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            if ($range == '-') {
                $c_start = $this->size - substr($range, 1);
            }else{
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
            }
            $c_end = ($c_end > $this->end) ? $this->end : $c_end;
            if ($c_start > $c_end || $c_start > $this->size - 1 || $c_end >= $this->size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            $this->start = $c_start;
            $this->end = $c_end;
            $length = $this->end - $this->start + 1;
            fseek($this->stream, $this->start);
            header('HTTP/1.1 206 Partial Content');
            header("Content-Length: ".$length);
            header("Content-Range: bytes $this->start-$this->end/".$this->size);
        }
        else
        {
            header("Content-Length: ".$this->size);
        }
        //stream
        $i = $this->start;
        set_time_limit(0);
        while(!feof($this->stream) && $i <= $this->end) {
            $bytesToRead = $this->buffer;
            if(($i+$bytesToRead) > $this->end) {
                $bytesToRead = $this->end - $i + 1;
            }
            $data = @stream_get_contents($this->stream, $bytesToRead, intval($i));
            echo $data;
            flush();
            $i += $bytesToRead;
        }

        //close
        fclose($this->stream);
        exit;
    }

}
