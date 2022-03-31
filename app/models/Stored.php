<?php /** @noinspection PhpUnused */

use Phalcon\Crypt;
use Phalcon\Http\Request\FileInterface;
use Phalcon\Mvc\Model;

/**
 * Class Stored
 * @property Link[] links
 * @property string storedPath
 * @property string passwordHash
 * @property FileInterface file
 * @property DateTime till
 * @property bool isImage
 * @property bool isPDF
 * @property bool isEncrypted
 * @property bool isNew
 */
class Stored extends Model
{
    /**
     * @var int
     */
    public $id;
    /**
     * @var FileInterface
     */
    protected $file;
    /**
     * @var string
     */
    public $storename;
    /**
     * @var string
     */
    public $origname;
    /**
     * @var null|string
     */
    protected $till = null;
    /**
     * @var string
     */
    public $mime;
    /**
     * @var Link
     */
    public $linkPub;
    /**
     * @var Link
     */
    public $linkSec;
    /**
     * @var string
     */
    public $password;
    /**
     * @var string
     */
    public $encrypt;
    /**
     * @var array
     */
    public $cropdata;

    public function initialize()
    {
        $this->setSource('storage');
        $this->hasMany(
            'id',
            Link::class,
            'file',
            ['alias' => 'links'],
        );
    }

    /**
     * @param FileInterface $file
     */
    public function setFile(FileInterface $file)
    {
        $this->file = $file;
    }

    public function getTill(): DateTime
    {
        $dt = null;
        try {
            $dt = new DateTime($this->till, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        } catch (Exception $e) {
        }
        return $dt;
    }

    public function setTill(DateTime $dt)
    {
        $dt->setTimezone(new DateTimeZone('UTC'));
        $this->till = $dt->format('Y-m-d H:i:s');
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getPasswordHash()
    {
        if (empty($this->password) || empty($this->encrypt)) throw new Exception('crypt pwd hash: empty pwd or salt');

        return hash_pbkdf2('sha256', $this->password, $this->encrypt, 10000, 256, true);
    }

    public function getStoredPath(): string
    {
        return STORAGE_PATH . $this->storename;
    }

    public function getIsImage(): bool
    {
        return preg_match('/^image\//', $this->mime) === 1;
    }

    public function getIsPDF(): bool
    {
        return preg_match('/^application\/pdf/', $this->mime) === 1;
    }

    public function getIsEncrypted(): bool
    {
        return !empty($this->encrypt);
    }

    public function getIsNew(): bool
    {
        return empty($this->id);
    }

    /**
     * @throws Crypt\Mismatch
     * @throws ImagickException
     * @throws \setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException
     * @throws \setasign\Fpdi\PdfParser\Filter\FilterException
     * @throws \setasign\Fpdi\PdfParser\PdfParserException
     * @throws \setasign\Fpdi\PdfParser\Type\PdfTypeException
     * @throws \setasign\Fpdi\PdfReader\PdfReaderException
     */
    public function prepareSave()
    {
        if ($this->isNew && empty($this->till)) {
            $this->till = date('Y-m-d H:i:s', strtotime('+2 weeks'));
        }

        if ($this->isNew) {
            $this->mime = $this->file->getRealType() ?? $this->file->getType();
            $this->origname = $this->file->getName();
            $this->storename = $this->unique();
        }

        if ($this->isImage && ($this->isNew || !empty($this->cropdata))) {
            $im = new Imagick($this->isNew ? $this->file->getTempName() : $this->storedPath);
            if (!empty($this->cropdata)) {
                $im->cropImage(
                    $this->cropdata['width'],
                    $this->cropdata['height'],
                    $this->cropdata['x'],
                    $this->cropdata['y']
                );
            }
            $im->writeImage($this->storedPath);
        } elseif ($this->isNew && $this->isPDF) {
            $pdf = new \setasign\Fpdi\Fpdi;
            $pcnt = $pdf->setSourceFile($this->file->getTempName());
            for ($i = 1; $i <= $pcnt; $i++) {
                $page = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($page);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($page);
            }
            $pdf->Output('F', $this->storedPath, true);
        } elseif ($this->isNew) {
            $this->file->moveTo($this->storedPath);
        }

        if (!empty($this->password)) {
            if ($this->isEncrypted) {
                $this->decrypt();
                $this->encrypt = null;
            } else {
                $this->encrypt();
                $this->password = null;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function afterCreate()
    {
        $linkLength = $this->getDI()->get('config')->path('app.link_length');
        $this->linkPub = new Link;
        $this->linkPub->save([
            'file' => $this->id,
            'link' => $this->unique($linkLength),
        ]);
        $this->linkSec = new Link;
        $this->linkSec->save([
            'file' => $this->id,
            'link' => $this->unique($linkLength),
            'secret' => true,
        ]);
    }

    public function afterDelete()
    {
        @unlink($this->storedPath);
    }

    /**
     * encrypt stored file
     * @throws Exception
     */
    public function encrypt()
    {
        if (empty($this->password)) return;
        /**
         * @var Crypt $crypt
         */
        $crypt = $this->getDI()->get('crypt');
        $text = file_get_contents($this->storedPath);
        $this->encrypt = $this->unique();
        $enctxt = $crypt->encrypt($text, $this->passwordHash);
        file_put_contents($this->storedPath, $enctxt);
    }

    /**
     * @throws Crypt\Mismatch
     */
    public function decrypt()
    {
        /**
         * @var Crypt $crypt
         */
        $crypt = $this->getDI()->get('crypt');
        $cryptedText = file_get_contents($this->storedPath);
        $dectxt = $crypt->decrypt($cryptedText, $this->passwordHash);
        file_put_contents($this->storedPath, $dectxt);
    }

    /**
     * @param int $length
     * @return string
     * @throws Exception
     */
    public function unique($length = 20): string
    {
        return base_convert(bin2hex(random_bytes($length)), 16, 36);
    }
}