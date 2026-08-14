<?php
declare(strict_types=1);

namespace Uskur\Attachments\Controller;

use Cake\Http\Exception\HttpException;
use Cake\Http\Exception\NotImplementedException;
use Cake\Http\Exception\UnprocessableContentException;
use Cake\ORM\TableRegistry;
use FilesystemIterator;
use Gumlet\ImageResize;
use Imagick;
use RuntimeException;
use Throwable;
use Uskur\Attachments\Model\Entity\Attachment;

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
     * @param string|null $model Model alias.
     * @param string|null $fk Foreign key value.
     * @return \Cake\Http\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add($model = null, $fk = null)
    {
        $attachment = $this->Attachments->newEmptyEntity();
        $files = [];
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
                    if ($attachment) {
                        $files[] = [
                            'id' => $attachment->id,
                            'name' => $attachment->filename,
                            'size' => $attachment->size,
                            'type' => $attachment->filetype,
                        ];
                    }
                }
            } else {
                $file = $this->request->getData('file');
                $attachment = $this->Attachments->addUpload($entity, $file);
                if ($attachment) {
                    $files[] = [
                        'id' => $attachment->id,
                        'name' => $attachment->filename,
                        'size' => $attachment->size,
                        'type' => $attachment->filetype,
                    ];
                }
            }
        }
        $this->set(compact('attachment', 'files'));
        $this->viewBuilder()->setOption('serialize', ['attachment', 'files']);

        if ($this->request->getParam('_ext') === 'json') {
            return null;
        }

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
        $attachment = $this->Attachments->get($id, contain: []);
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
        $this->viewBuilder()->setOption('serialize', ['attachment']);
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
     *
     * @param string $id Attachment ID.
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
                //validate quality
                if (
                    $option == 'q' && (
                    !is_numeric($this->request->getQuery($option)) ||
                    $this->request->getQuery($option) > 100) ||
                    $this->request->getQuery($option) < 0
                ) {
                    throw new \Exception('Invalid quality parameter.');
                }
                //validate height and width
                if (
                    ($option == 'w' || $option == 'h') && (
                    !is_numeric($this->request->getQuery($option)) ||
                    $this->request->getQuery($option) < 0
                    )
                ) {
                    throw new \Exception('Invalid height/width parameter.');
                }
                //validate crop and enlarge
                if (
                    ($option == 'c' || $option == 'e') && (
                        $this->request->getQuery($option) != 0 &&
                        $this->request->getQuery($option) != 1
                    )
                ) {
                    throw new \Exception('Invalid crop/enlarge parameter.');
                }
                //validate mode
                if ($option == 'm' && $this->request->getQuery($option) != 'fill') {
                    throw new \Exception('Invalid mode parameter.');
                }
                //validate fill color
                if ($option == 'fc' && !preg_match('/^[a-f0-9]{6}$/i', $this->request->getQuery($option))) {
                    throw new \Exception('Invalid fill color parameter.');
                }

                $value = $this->request->getQuery($option);
                if (in_array($option, ['w', 'h', 'c', 'e', 'q'], true)) {
                    $value = (int)$value;
                }

                $options[$option] = $value;
            } elseif ($option == 'fc') {
                // Default fill color to white.
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
            $temporaryDirectory = null;
            try {
                [$imagePath, $temporaryDirectory] = $this->previewImageSource($attachment);
                $image = new ImageResize($imagePath);
                if ($options['m'] == 'fill') {
                    //resize and temporarily save
                    $image->resizeToBestFit($options['w'], $options['h'], $options['e']);
                    $tempImage = '/tmp/' . rand();
                    $image->save($tempImage, IMAGETYPE_JPEG);

                    $image = new ImageResize($imagePath);
                    $image->resize($options['w'], $options['h'], true);
                    $image->addFilter(function ($imageDesc) use ($options, $tempImage): void {
                        [$r, $g, $b] = sscanf($options['fc'], '%02x%02x%02x');
                        $backgroundColor = imagecolorallocate($imageDesc, $r, $g, $b);
                        imagefilledrectangle($imageDesc, 0, 0, $options['w'], $options['h'], $backgroundColor);

                        $resizedImage = imagecreatefromjpeg($tempImage);
                        $imageHeight = imagesy($resizedImage);
                        $imageWidth = imagesx($resizedImage);
                        $destinationY = 0;
                        //position resized image
                        if ($options['h'] > $imageHeight) {
                            $destinationY = (int)(($options['h'] - $imageHeight) / 2);
                        }
                        $destinationX = 0;
                        if ($options['w'] > $imageWidth) {
                            $destinationX = (int)(($options['w'] - $imageWidth) / 2);
                        }
                        imagecopy(
                            $imageDesc,
                            $resizedImage,
                            $destinationX,
                            $destinationY,
                            0,
                            0,
                            $imageWidth,
                            $imageHeight,
                        );
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
                    //modify quality imagejpeg to imagepng
                    if (!is_null($options['q'])) {
                        $options['q'] = (int)round((100 - $options['q']) / 10);
                    }
                }
                $image->save($cacheFile, $options['type'], $options['q']);
            } finally {
                if ($temporaryDirectory !== null) {
                    $this->removeDirectory($temporaryDirectory);
                }
            }
        }
        if (!file_exists($cacheFile)) {
            throw new \Exception("File {$cacheFile} cannot be read.");
        }
        $cacheMime = mime_content_type($cacheFile);
        $cacheMTime = filemtime($cacheFile);
        if ($cacheMime === false || $cacheMTime === false) {
            throw new \Exception("File {$cacheFile} cannot be read.");
        }
        $response = $this->response->withFile(
            $cacheFile,
            ['download' => false, 'name' => (isset($attachment) ? $attachment->filename : null)]
        )
            ->withVary('Accept')
            ->withType($cacheMime)
            ->withCache('-1 minute', '+6 month')
            ->withExpires('+6 month')
            ->withMustRevalidate(false)
            ->withModified($cacheMTime);

        if ($options['type'] == IMAGETYPE_WEBP) {
            $response = $response->withSharable(false);
        }

        if ($response->isNotModified($this->request)) {
            return $response->withNotModified();
        }

        return $response;
    }

    /**
     * Return an image-readable source for an attachment.
     *
     * Non-image previews optionally use LibreOffice. PDF and converted document
     * sources are rasterized to their first page/slide in a temporary directory.
     *
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Attachment.
     * @return array{0: string, 1: string|null} Image path and temporary directory.
     */
    private function previewImageSource(Attachment $attachment): array
    {
        if (str_starts_with($attachment->filetype, 'image/')) {
            return [$attachment->path, null];
        }
        if ($attachment->filetype !== 'application/pdf' && !$this->supportsDocumentPreview($attachment)) {
            throw new HttpException('Preview generation is not supported for this file type.', 415);
        }

        $temporaryDirectory = $this->createTemporaryDirectory();
        try {
            $pdfPath = $attachment->path;
            if ($attachment->filetype !== 'application/pdf') {
                $pdfPath = $this->convertDocument($attachment, $temporaryDirectory);
            }

            $imagePath = $temporaryDirectory . DS . 'preview.jpg';
            $imagick = new Imagick();
            try {
                $imagick->setResolution(300, 300);
                $imagick->readImage($pdfPath . '[0]');
                $imagick->setImageBackgroundColor('white');
                $imagick->setImageFormat('jpg');
                $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $imagick->writeImage($imagePath);
            } finally {
                $imagick->clear();
                $imagick->destroy();
            }

            return [$imagePath, $temporaryDirectory];
        } catch (Throwable $exception) {
            $this->removeDirectory($temporaryDirectory);
            throw $exception;
        }
    }

    /**
     * Check whether an attachment belongs to a LibreOffice document family.
     *
     * The original filename extension is intentionally allowlisted before the
     * file is passed to LibreOffice. MIME types alone are not sufficiently
     * reliable for uploaded Office documents.
     *
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Attachment.
     * @return bool
     */
    private function supportsDocumentPreview(Attachment $attachment): bool
    {
        $supportedExtensions = [
            // Writer
            'doc', 'docx', 'docm', 'dot', 'dotx', 'dotm',
            'odt', 'ott', 'fodt', 'rtf', 'sxw', 'stw', 'wps',
            // Calc
            'xls', 'xlsx', 'xlsm', 'xlsb', 'xlt', 'xltx', 'xltm',
            'ods', 'ots', 'fods', 'sxc', 'stc', 'csv', 'tsv',
            // Impress
            'ppt', 'pptx', 'pptm', 'pot', 'potx', 'potm',
            'pps', 'ppsx', 'ppsm', 'odp', 'otp', 'fodp', 'sxi', 'sti',
            // Draw and other office document formats
            'odg', 'otg', 'fodg', 'vsd', 'vsdx', 'vdx', 'pub',
        ];

        return in_array(strtolower((string)$attachment->extension), $supportedExtensions, true);
    }

    /**
     * Ask LibreOffice to convert an attachment to PDF.
     *
     * @param \Uskur\Attachments\Model\Entity\Attachment $attachment Attachment.
     * @param string $temporaryDirectory Conversion directory.
     * @return string Generated PDF path.
     */
    private function convertDocument(Attachment $attachment, string $temporaryDirectory): string
    {
        $binary = $this->findExecutable(['libreoffice', 'soffice']);
        if ($binary === null) {
            throw new NotImplementedException('Document previews require LibreOffice.');
        }

        $extension = strtolower((string)$attachment->extension);
        $inputPath = $temporaryDirectory . DS . 'document' . ($extension !== '' ? '.' . $extension : '');
        if (!copy($attachment->path, $inputPath)) {
            throw new UnprocessableContentException('The document could not be prepared for preview.');
        }

        $profileDirectory = $temporaryDirectory . DS . 'libreoffice-profile';
        if (!mkdir($profileDirectory, 0700)) {
            throw new RuntimeException('The LibreOffice profile directory could not be created.');
        }
        $profileUri = 'file://' . str_replace(DIRECTORY_SEPARATOR, '/', $profileDirectory);
        $command = [
            $binary,
            '-env:UserInstallation=' . $profileUri,
            '--headless',
            '--norestore',
            '--nodefault',
            '--nolockcheck',
            '--nofirststartwizard',
            '--convert-to',
            'pdf',
            '--outdir',
            $temporaryDirectory,
            $inputPath,
        ];
        $this->runProcess($command, 30);

        $pdfPath = $temporaryDirectory . DS . 'document.pdf';
        if (!is_file($pdfPath)) {
            throw new UnprocessableContentException(
                'LibreOffice does not support this document or could not produce a preview.',
            );
        }

        return $pdfPath;
    }

    /**
     * Run a command with a time limit and without invoking a shell.
     *
     * @param array<string> $command Command and arguments.
     * @param int $timeout Timeout in seconds.
     * @return void
     */
    private function runProcess(array $command, int $timeout): void
    {
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            throw new NotImplementedException('LibreOffice could not be started.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $startedAt = microtime(true);
        $status = proc_get_status($process);
        while ($status['running']) {
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            if (microtime(true) - $startedAt >= $timeout) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new UnprocessableContentException('Document preview generation timed out.');
            }
            usleep(100000);
            $status = proc_get_status($process);
        }

        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeCode = proc_close($process);
        $exitCode = $status['exitcode'] >= 0 ? $status['exitcode'] : $closeCode;
        if ($exitCode !== 0) {
            throw new UnprocessableContentException('LibreOffice could not convert this document.');
        }
    }

    /**
     * Find the first executable in PATH.
     *
     * @param array<string> $names Executable names.
     * @return string|null
     */
    private function findExecutable(array $names): ?string
    {
        $pathDirectories = explode(PATH_SEPARATOR, (string)getenv('PATH'));
        foreach ($names as $name) {
            foreach ($pathDirectories as $directory) {
                $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                if (is_file($path) && is_executable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Create a private temporary directory.
     *
     * @return string
     */
    private function createTemporaryDirectory(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'attachment-preview-');
        if ($path === false || !unlink($path) || !mkdir($path, 0700)) {
            throw new RuntimeException('A temporary preview directory could not be created.');
        }

        return $path;
    }

    /**
     * Remove a temporary directory and its contents.
     *
     * @param string $directory Directory path.
     * @return void
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new FilesystemIterator($directory) as $file) {
            if ($file->isDir() && !$file->isLink()) {
                $this->removeDirectory($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($directory);
    }

    /**
     * Stream an attachment inline without forcing download.
     *
     * @param string $id Attachment ID.
     * @param string|null $name Download name override.
     * @return \Cake\Http\Response|null
     */
    public function file($id, $name = null)
    {
        $attachment = $this->Attachments->get($id);
        if (!file_exists($attachment->path)) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }
        $lastModified = filemtime($attachment->path);
        if ($lastModified === false) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }

        $response = $this->response->withFile(
            $attachment->path,
            ['download' => false, 'name' => $attachment->filename]
        )
            ->withType($attachment->filetype)
            ->withCache('-1 minute', '+6 month')
            ->withExpires('+6 month')
            ->withMustRevalidate(false)
            ->withModified($lastModified)
            ->withSharable(true);

        if ($response->isNotModified($this->request)) {
            return $response->withNotModified();
        }

        return $response;
    }

    /**
     * Download an attachment.
     *
     * @param string $id Attachment ID.
     * @param string|null $name Download name override.
     * @return \Cake\Http\Response|null
     */
    public function download($id, $name = null)
    {
        $attachment = $this->Attachments->get($id);
        if (!file_exists($attachment->path)) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }
        $lastModified = filemtime($attachment->path);
        if ($lastModified === false) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }

        $response = $this->response->withFile(
            $attachment->path,
            ['download' => true, 'name' => $attachment->filename]
        )
            ->withType($attachment->filetype)
            ->withCache('-1 minute', '+6 month')
            ->withExpires('+6 month')
            ->withMustRevalidate(false)
            ->withModified($lastModified);

        if ($response->isNotModified($this->request)) {
            return $response->withNotModified();
        }

        return $response;
    }

    /**
     * Update the sequence position of an attachment.
     *
     * @param string|null $id Attachment ID.
     * @param int|string|null $newPosition New position.
     * @return void
     */
    public function updatePosition($id = null, $newPosition = null)
    {
        $attachment = $this->Attachments->get($id);
        $attachment->sequence = $newPosition;
        $this->Attachments->save($attachment);
        $this->set('attachment', $attachment);
        exit;
    }

    /**
     * Reorder attachments for a given foreign key.
     *
     * @param string $fk Foreign key.
     * @return void
     */
    public function reorder($fk)
    {
        $reorder = $this->Attachments->find(
            'all',
            fields: ['id'],
            conditions: ['foreign_key' => $fk]
        )->orderBy(['filename ASC'])->toArray();
        $this->Attachments->setOrder($reorder);
    }

    /**
     * Render the attachment list for an owner model.
     *
     * @param string $model Model alias.
     * @param string $fk Foreign key.
     * @return void
     */
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
        $this->viewBuilder()->setOption('serialize', ['attachments']);
    }

    /**
     * Edit custom detail attributes for an attachment.
     *
     * @param string $id Attachment ID.
     * @return \Cake\Http\Response|null
     */
    public function fileAttribute($id)
    {
        $attachment = $this->Attachments->get($id);
        if ($this->request->is('post')) {
            $attachmentDetails = $attachment->details;
            foreach ($this->request->getData() as $detail => $value) {
                if ($detail == 'id') {
                    continue;
                }
                $attachmentDetails[$detail] = $value;
            }
            $attachment->details = $attachmentDetails;
            $this->Attachments->save($attachment);

            return $this->redirect($this->referer());
        }

        $this->set('attachment', $attachment);
        $this->viewBuilder()->setOption('serialize', ['attachment']);
    }

    /**
     * Stream a video attachment with byte-range support.
     *
     * @param string $id Attachment ID.
     * @param string|null $name Stream name override.
     * @return void
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
        $this->stream = fopen($attachment->path, 'rb');
        if (!$this->stream) {
            throw new \Exception('Stream could not be opened.');
        }

        ob_get_clean();
        header('Content-Type: video/mp4');
        header('Cache-Control: max-age=311040000, public');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 311040000) . ' GMT');
        $lastModified = filemtime($attachment->path);
        if ($lastModified === false) {
            throw new \Exception("File {$attachment->path} cannot be read.");
        }
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        $this->start = 0;
        $this->size = filesize($attachment->path);
        $this->end = $this->size - 1;

        header('Accept-Ranges: 0-' . $this->end);
        //set header
        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $this->start;
            $c_end = $this->end;

            [, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            if ($range == '-') {
                $c_start = $this->size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = isset($range[1]) && is_numeric($range[1]) ? $range[1] : $c_end;
            }
            $c_end = $c_end > $this->end ? $this->end : $c_end;
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
            header('Content-Length: ' . $length);
            header("Content-Range: bytes $this->start-$this->end/" . $this->size);
        } else {
            header('Content-Length: ' . $this->size);
        }
        //stream
        $i = $this->start;
        set_time_limit(0);
        while (!feof($this->stream) && $i <= $this->end) {
            $bytesToRead = $this->buffer;
            if ($i + $bytesToRead > $this->end) {
                $bytesToRead = $this->end - $i + 1;
            }
            $data = stream_get_contents($this->stream, $bytesToRead, intval($i));
            if ($data === false) {
                throw new \Exception('Stream could not be read.');
            }
            echo $data;
            flush();
            $i += $bytesToRead;
        }

        //close
        fclose($this->stream);
        exit;
    }

    /**
     * Edit an image attachment in-place using a base64 payload.
     *
     * @param string $id Attachment ID.
     * @return \Cake\Http\Response|void
     */
    public function editImage($id)
    {
        $image = $this->Attachments->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $tempPath = tempnam('/tmp', 'replace');
            $img = str_replace('data:image/png;base64,', '', $this->request->getData('image'));
            $img = str_replace(' ', '+', $img);
            file_put_contents($tempPath, base64_decode($img));

            if ($this->Attachments->replaceFile($id, $tempPath)) {
                $this->Flash->success(__('Image modified.'));
                $redirectTo = $this->getRequest()->getSession()->consume('Attachment.redirectAfter');
                if ($redirectTo) {
                    return $this->redirect($redirectTo);
                }
            } else {
                $this->Flash->error(__('Image could not be saved. Please, try again.'));
            }
        } else {
            $this->getRequest()->getSession()->write('Attachment.redirectAfter', $this->referer());
        }
        $this->set('image', $image);
    }
}
