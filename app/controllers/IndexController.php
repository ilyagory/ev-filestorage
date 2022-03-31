<?php /** @noinspection PhpUnused */

use App\Util\HttpException as UtilHttpException;
use App\Util\NotFoundException;
use Phalcon\Config\Adapter\Ini;
use Phalcon\Filter;
use Phalcon\Http\ResponseInterface;
use Phalcon\Logger\AdapterInterface;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\View;

/**
 * Class IndexController
 * @property AdapterInterface $log
 * @property Ini $config
 */
class IndexController extends Controller
{
    public function indexAction()
    {
        $dtFmt = 'Y-m-d\TH:i';
        $rb = function ($val) {
            $val = (int)trim($val);
            $last = strtolower($val[strlen($val) - 1]);
            switch ($last) {
                // The 'G' modifier is available
                case 'g':
                    $val *= 1024;
                case 'm':
                    $val *= 1024;
                case 'k':
                    $val *= 1024;
            }

            return $val;
        };
        $fsize = $rb(ini_get('upload_max_filesize'));
        $this->view->setVars([
            'tokenKey' => $this->security->getTokenKey(),
            'tokenValue' => $this->security->getToken(),
            'action' => $this->url->get(['for' => 'app.upload']),
            'minDate' => date($dtFmt),
            'maxDate' => date($dtFmt, strtotime('+2 week')),
            'maxFilesize' => $fsize,
            'maxPwdLength' => (int)$this->config->path('app.max_pwd_length', 8),
        ]);
    }

    /**
     * @param string $lnk
     * @return bool|ResponseInterface|View
     * @throws Exception
     */
    public function showAction(string $lnk)
    {
        $link = $this->getLinkEntity($lnk);
        if ($link->secret) {
            $links = $link->stored->links;
            $pubLink = null;
            $lnks = [];
            foreach ($links as $l) {
                $lnk = $this->url->get(['for' => 'app.show', 'link' => $l->link]);
                if (!$l->secret) $pubLink = $lnk;

                $ll = new stdClass();
                $ll->link = $lnk;
                $ll->secret = $l->secret;
                $lnks[] = $ll;
            }
            $this->view
                ->setVars([
                    'tokenKey' => $this->security->getTokenKey(),
                    'tokenValue' => $this->security->getToken(),
                    'action' => $this->url->get([
                        'for' => 'app.update',
                        'link' => $link->link,
                    ]),
                    'deleteAction' => $this->url->get([
                        'for' => 'app.delete',
                        'link' => $link->link,
                    ]),
                    'stored' => $link->stored,
                    'links' => $lnks,
                    'isImage' => $link->stored->isImage,
                    'pubLink' => $pubLink,
                    'goHomeLink' => $this->url->get(['for' => 'app.main']),
                    'maxPwdLength' => (int)$this->config->path('app.max_pwd_length', 8),
                ])
                ->pick('index/edit');
            return true;
        }

        $this->response
            ->setFileToSend(
                STORAGE_PATH . $link->stored->storename,
                $link->stored->origname
            )
            ->setContentType($link->stored->mime);
        return false;
    }

    /**
     * @param string $lnk
     * @return false
     * @throws Exception
     */
    public function deleteAction(string $lnk): bool
    {
        if (!$this->security->checkToken()) throw new NotFoundException;
        $link = $this->getLinkEntity($lnk, true);
        if ($link->stored->delete()) {
            $this->response->redirect(
                $this->url->get(['for' => 'app.main']),
                false,
                303
            );
            return false;
        }
        throw new Exception(print_r($link->stored->getMessages(), true));
    }

    /**
     * @throws Exception
     */
    public function uploadAction(): ResponseInterface
    {
        if (!$this->security->checkToken()) throw new NotFoundException;
        if (!$this->request->hasFiles(true)) throw new NotFoundException;

        $stored = new Stored;
        $file = null;
        if ($this->request->hasFiles(true)) {
            $files = $this->request->getUploadedFiles(true);
            if (count($files) > 0) $file = reset($files);
        }

        if ($file === null) throw new Exception('Attachment is empty');

        $stored->file = $file;
        $this->saveStoredEntity($stored);

        return $this->response->redirect(
            $this->url->get(['for' => 'app.show', 'link' => $stored->linkSec->link]),
            false,
            303
        );
    }

    /**
     * @param string $lnk
     * @throws Exception
     */
    public function updateAction(string $lnk)
    {
        if (!$this->security->checkToken()) throw new NotFoundException;

        $link = $this->getLinkEntity($lnk, true);
        if (empty($link)) throw new NotFoundException;

        $this->saveStoredEntity($link->stored);

        $this->response->redirect(
            $this->url->get(['for' => 'app.show', 'link' => $link->link]),
            false,
            303
        );
    }

    /**
     * @param Exception $exception
     */
    public function errorAction(Exception $exception)
    {
        $this->log->error(
            $exception->getMessage() .
            "\n" .
            $exception->getTraceAsString()
        );
        $status = 500;
        $text = UtilHttpException::TXT_INTERNAL_SERVER;
        if ($exception instanceof UtilHttpException) {
            $status = $exception->getCode();
            $text = $exception->getMessage();
        }
        if ($exception instanceof \Phalcon\Mvc\Dispatcher\Exception) {
            switch ($exception->getCode()) {
                case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                    $status = 404;
                    $text = UtilHttpException::TXT_NOT_FOUND;
            }
        }
        $this->response->setStatusCode($status);
        $this->view->setVar('error', $text);
    }

    /**
     * @param string $lnk
     * @param bool|null $secret
     * @return Link
     * @throws NotFoundException
     */
    protected function getLinkEntity(string $lnk, $secret = null): Link
    {
        $q = Link::query()->where('link = ?0');
        $bindings = [$lnk];
        if ($secret !== null) {
            $q->andWhere('secret = ?1');
            $bindings[] = (bool)$secret;
        }
        /**
         * @var Link $link
         */
        $link = $q->bind($bindings)->execute()->getFirst();
        if (empty($link)) throw new NotFoundException;
        return $link;
    }

    /**
     * @param Stored $stored
     * @return bool|mixed
     */
    protected function saveStoredEntity(Stored $stored): bool
    {
        if ($stored->isNew) {
            $tillDate = $this->request->getPost('tillDate');
            if (!empty($tillDate)) {
                try {
                    $stored->till = new DateTime($tillDate);
                } catch (Exception $e) {
                }
            }
        }

        $pwd = $this->request->getPost('password', Filter::FILTER_STRING);
        if (!empty($pwd)) $stored->password = $pwd;

        $cropdata = $this->request->getPost('cropdata');
        if (!empty($cropdata)) {
            try {
                $stored->cropdata = json_decode($cropdata, true);
            } catch (Exception $e) {
            }
        }

        $saved = $stored->save();
        if ($saved === false) {
            $this->log->error(implode("\n", $stored->getMessages()));
        }
        return $saved;
    }
}