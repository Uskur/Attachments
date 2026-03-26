<?php
declare(strict_types=1);

namespace Uskur\Attachments\Controller;

use App\Controller\AppController as BaseController;
use Cake\View\JsonView;

class AppController extends BaseController
{
    /**
     * Return the negotiated view classes supported by the plugin.
     *
     * @return array
     */
    public function viewClasses(): array
    {
        return [
            JsonView::class,
        ];
    }
}
