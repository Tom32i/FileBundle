<?php

namespace Tom32i\FileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;
use Doctrine\Annotations;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Tom32i\FileBundle\Entity\File
 *
 * @ORM\MappedSuperclass
 * @ORM\HasLifecycleCallbacks()
 */
abstract class File
{
    const TYPE_FILE = 0;
    const TYPE_IMAGE = 1;
    const TYPE_DOC = 2;
    const TYPE_PDF = 3;
    const TYPE_EXCEL = 4;
    const TYPE_ARCHIVE = 5;

    protected $id;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @var string $path
     *
     * @ORM\Column(name="path", type="string", length=255, nullable=true)
     */
    protected $path;
    
    /**
     * @var string $filename
     *
     * @ORM\Column(name="filename", type="string", length=255, nullable=true)
     */
    protected $filename;

    /**
     * @var datetime $created
     *
     * @ORM\Column(name="created", type="datetime")
     */
    protected $created;

    /**
     * @var smallint $type
     *
     * @ORM\Column(name="type", type="smallint")
     */
    protected $type;
    
    public $file;
    
    protected $paterns;
    protected $keep_full_image = false;

    private $ext;
    private $secured_name;

    /* META */

    public function __construct()
    {
        $this->created = new \DateTime();
    }
    
    public function __toString()
    {
        return $this->getWebPath();
    }

    /* EVENTS */

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function preUpload()
    {
        if (null === $this->file) {
            return;
        }
        
        $this->detectPath();
        $this->detectExtension();
        $this->detectType();
        $this->detectFileName();
    }

    /**
     * @ORM\PostPersist()
     * @ORM\PostUpdate()
     */
    public function upload()
    {
        // the file property can be empty if the field is not required
        if (null === $this->file) {
            return;
        }
        
        // move takes the target directory and then the target filename to move to
        $this->file->move($this->getAbsoluteDir(), $this->filename);

        // clean up the file property as you won't need it anymore
        $this->file = null;

        $this->removePaterns();
        
        /*if(!empty($patern))
        {
            $resize_path = $this->getImageFile(true, $patern);
            $default_path = $path.'/'.$this->filename;
            
            if($resize_path)
            {   
                unlink($default_path);
                copy($resize_path, $default_path);
            }
        }*/
    }

    /**
     * @ORM\PostRemove()
     */
    public function removeUpload()
    {
        if($this->filename)
        {
            $this->removePaterns();

            $file = $this->getAbsolutePath();

            if (file_exists($file)) 
            {
                unlink($file);
            }
        }
    }

    /* METHODS */

    protected function defaultPath()
    {
        return null;
    }

    protected function defaultName()
    {
        if($this->file)
        {
            return self::readName($this->file->getClientOriginalName());
        }
        else
        {
            return $this->getTypeName() . '_' . uniqid();
        }
    }

    private function detectPath()
    {
        $this->path = $this->defaultPath();
    }

    private function detectExtension()
    {
        $this->ext = strtolower($this->file->guessExtension());
        if($this->ext == 'jpeg'){ $this->ext = 'jpg'; }
    }

    private function detectName()
    {
        if(empty($this->name))
        {
            $this->name = $this->defaultName();
        }

        $this->secureName();
    }

    private function detectFileName()
    {
        $this->detectName();

        $this->filename = $this->secured_name . '.' . $this->ext;
    }
    
    private function detectType()
    {
        switch ($this->ext) 
        {
            case 'jpg':
            case 'jpeg':
            case 'gif':
            case 'png':
            case 'bmp':
            case 'tiff':
                $this->type = self::TYPE_IMAGE;
                break;

            case 'doc':
            case 'docx':
                $this->type = self::TYPE_DOC;
                break;

            case 'pdf':
                $this->type = self::TYPE_PDF;
                break;

            case 'xls':
            case 'xlsx':
                $this->type = self::TYPE_EXCEL;
                break;

            case 'zip':
            case 'rar':
            case 'tar':
            case 'gz':
                $this->type = self::TYPE_ARCHIVE;
                break;
            
            default:
                $this->type = self::TYPE_FILE;
                break;
        }
    }

    private function getTypeName()
    {
        switch ($this->type)
        {
            case self::TYPE_IMAGE:
                return "Image";

            case self::TYPE_DOC:
                return "Word document";

            case self::TYPE_PDF:
                return "PDF Document";

            case self::TYPE_EXCEL:
                return "Excel document";

            case self::TYPE_ARCHIVE:
                return "Archive";

            default: 
                return "File";
        }
    }

    public static function getTypes()
    {
        return array(
            self::TYPE_FILE,
            self::TYPE_IMAGE,
            self::TYPE_DOC,
            self::TYPE_PDF,
            self::TYPE_EXCEL,
            self::TYPE_ARCHIVE,
        );
    }

    public function removePaterns()
    {
        if($this->filename)
        {
            foreach ($this->paterns as $key => $value) 
            {
                $file = $this->getAbsolutePath($key);
                $retina_file = self::retina($file);
            
                if (file_exists($file)) 
                {
                    unlink($file);
                }

                if (file_exists($retina_file)) 
                {
                    unlink($retina_file);
                }
            }
        }
    }

    private function getAbsoluteDir($patern = null)
    {
        return $this->getUploadRootDir() . (empty($this->path) ? '' : '/' . $this->path) . (empty($patern) ? '' : '/' . $patern);
    }

    private function getWebDir($patern = null)
    {
        return '/' . $this->getUploadDir() . (empty($this->path) ? '' : '/'.$this->path) . (empty($patern) ? '' : '/' . $patern);
    }
    
    public function getAbsolutePath($patern = null)
    {
        return $this->getAbsoluteDir($patern) . '/' . $this->filename;
    }

    public function getWebPath($patern = null)
    {
        return $this->getWebDir($patern) . '/' . $this->filename;
    }

    /**
    *   The absolute directory path where uploaded documents should be saved
    **/
    protected function getUploadRootDir()
    {
        return __DIR__.'/../../../../web/'.$this->getUploadDir();
    }

    /**
    *   The main upload directory
    **/
    protected function getUploadDir()
    {
        return 'uploads';
    }
    
    private function getPaternOptions($patern)
    {
        if(array_key_exists($patern, $this->paterns))
        {
            if(!array_key_exists('retina', $this->paterns[$patern]))
            {
                $this->paterns[$patern]['retina'] = true;
            }

            return $this->paterns[$patern];
        }
        
        return null;
    }

    public function display($options = array())
    {
        if(is_string($options))
        {
            $options = array('patern' => $options);
        }
        
        switch($this->type)
        {
            case self::TYPE_IMAGE :
                $patern = null;
                
                if(array_key_exists('patern', $options))
                {
                    $patern = $options['patern'];
                    $patern_options = $this->getPaternOptions($patern);
                    
                    if($patern_options)
                    {
                       $params = array('width', 'height');
                       
                       foreach($params as $p)
                       {
                           $options[$p] = array_key_exists($p, $patern_options) ? $patern_options[$p] : null; 
                       }
                    }
                }
                
                $data = $this->getImageFile(false, $patern);

                foreach ($data as $key => $value) 
                {
                    $options[$key] = $value;
                }

                $patern_options = $this->getPaternOptions($patern);
                if($patern_options['retina'])
                {
                    $options['retina'] = self::retina($options['src']);
                }
                
                if(array_key_exists('toggle', $options))
                {
                    $options['toggle'] = $this->getImageFile(false, $options['toggle']);
                }
            break;
        }
        
        return $options;
    }
    
    public function getImageContent($patern, $response)
    {
        $data = $this->getImageFile(true, $patern);
        $file = $data['src'];
        
        $headers = array(
            'Content-Type' => 'image/'.substr($file, strrpos($file,'.')+1),
            'Content-Length' => filesize($file),
        );     
        
        $response->headers = new ResponseHeaderBag($headers);    
        $response->setContent(file_get_contents($file));
        
        return $response;
    }
    
    public function getImageFile($absolute, $patern = null)
    {
        if($this->type != self::TYPE_IMAGE){
            return;
        }
        
        if(!empty($patern))
        {
            $options = $this->getPaternOptions($patern);

            if($options === null){
                return;
            }
        }
        
        $filename = $this->getFilename();    
        
        $original_path = $this->getUploadRootDir() . '/' . $this->path;
        $patern_path = $original_path.(empty($patern) ? '' : '/' . $patern);
        $file = $patern_path . '/' . $filename;
        $loadfile = $original_path . '/' . $filename;
        $ext = self::readExt($filename);
        $create = false;
        
        if(!file_exists($original_path . '/' . $filename))
        {
            return false;
        }
        
        if(!file_exists($file))
        {   
            if(!file_exists($patern_path))
            {
                mkdir($patern_path);
            }
            
            $create = true;
            $patern_options = $this->getPaternOptions($patern);
            $patern_retina = false;

            if($patern_options['retina'])
            {
                $patern_retina = $patern_options;
                $file_retina = self::retina($file);

                if(array_key_exists('width', $patern_retina))
                {
                    $patern_retina['width'] = $patern_retina['width'] * 2;
                }

                if(array_key_exists('height', $patern_retina))
                {
                    $patern_retina['height'] = $patern_retina['height'] * 2;
                }
            }
            
            switch($ext)
            {
                case 'jpg':    
                    $image = $this::processImage($loadfile, imagecreatefromjpeg($loadfile), $patern_options);
                    imagejpeg($image['thumb'], $file, 100);

                    if($patern_retina)
                    {
                        $retina = $this::processImage($loadfile, imagecreatefromjpeg($loadfile), $patern_retina);
                        imagejpeg($retina['thumb'], $file_retina, 100);
                    }
                break;
                case 'png':      
                    $image = $this::processImage($loadfile, imagecreatefrompng($loadfile), $patern_options, true);
                    imagepng($image['thumb'], $file, 9);

                    if($patern_retina)
                    {
                        $retina = $this::processImage($loadfile, imagecreatefrompng($loadfile), $patern_retina);
                        imagepng($retina['thumb'], $file_retina, 9);
                    }
                break;
                case 'gif':  
                    $image = $this::processImage($loadfile, imagecreatefromgif($loadfile), $patern_options);
                    imagegif($image['thumb'], $file);

                    if($patern_retina)
                    {
                        $retina = $this::processImage($loadfile, imagecreatefromgif($loadfile), $patern_retina);
                        imagegif($retina['thumb'], $file_retina);
                    }
                break;
            }
        }

        if(!isset($image))
        {
            $size = getimagesize($file);
            $image = array('width' => $size[0], 'height' => $size[1]);
        }

        return array(
            'src' => $absolute ? $file : $this->getWebDir($patern) . '/' . $filename,
            'width' => $image['width'],
            'height' => $image['height'],
        );
    }
    
    static private function processImage($file, $image, $options, $alpha = false)
    {  
        $data = array();
        $dst_x = 0;
        $dst_y = 0;
        $src_x = 0;
        $src_y = 0;
        
        $dst_width = array_key_exists('width', $options) ? $options['width'] : null;
        $dst_height = array_key_exists('height', $options) ? $options['height'] : null;
        $method = array_key_exists('method', $options) ? $options['method'] : 'default';
        $enlarge = array_key_exists('enlarge', $options) ? $options['enlarge'] : false;
        
        $datas = getimagesize($file);
        $src_width = $datas[0];
        $src_height = $datas[1];

        $new_width = $src_width;
        $new_height = $src_height;
        
        unset($datas);
        
        $ratio_src = $src_width/$src_height; 
        
        if($dst_width != null && $dst_height != null)
        {
            $ratio_dst = $dst_width/$dst_height;
            
            if($ratio_src > $ratio_dst)
            {
                if($method == 'fill')
                {
                    $new_width = floor(($dst_height * $src_width) / $src_height);
                    $new_height = $dst_height;
                    $dst_x = floor(($dst_width - $new_width)/2);
                }
                elseif($src_width > $dst_width || $enlarge)
                {
                    $new_width = $dst_width;
                    $new_height = floor(($dst_width * $src_height) / $src_width);
                }
            }
            elseif($ratio_src < $ratio_dst)
            {
                if($method == 'fill')
                {
                    $new_width = $dst_width;
                    $new_height =  floor(($dst_width * $src_height) / $src_width);
                    $dst_y = floor(($dst_height - $new_height)/2);
                }
                elseif($src_height > $dst_height || $enlarge)
                {
                    $new_width = floor(($dst_height * $src_width) / $src_height);
                    $new_height = $dst_height;
                }
            }
            elseif($enlarge)
            {
                $new_width = $dst_width;
                $new_height = $dst_height;
            }
        }
        elseif($dst_width === null)
        {
            if($src_height > $dst_height || $enlarge)
            {
                $new_height = $dst_height;
                $new_width = floor(($dst_height * $src_width) / $src_height);
            }
        }
        elseif($dst_height === null)
        {
            if($src_width > $dst_width || $enlarge)
            {
                $new_width = $dst_width;
                $new_height = floor(($dst_width * $src_height) / $src_width);
            }
        }
        
        if($method == 'fill')
        {
            if(empty($dst_width)){ $dst_width = $new_width; }
            if(empty($dst_height)){ $dst_height = $new_height; }

            $thumb = imagecreatetruecolor($dst_width, $dst_height);

            $data['width'] = $dst_width;
            $data['height'] = $dst_height;
        }
        else
        {
            $thumb = imagecreatetruecolor($new_width, $new_height);
            
            $data['width'] = $new_width;
            $data['height'] = $new_height;
        }
        
        if($alpha)
        {
            imagealphablending($thumb, false);
            imagesavealpha($thumb,true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $dst_width, $dst_height, $transparent);
        }
        
        imagecopyresampled($thumb, $image, $dst_x, $dst_y, $src_x, $src_y, $new_width, $new_height, $src_width, $src_height);
        
        unset($image);
        
        $data['thumb'] = $thumb;

        return $data;
    }
    
    private function secureName()
    {
        $secured_name = strtolower($this->name);
        $secured_name = preg_replace('#[^a-z0-9_.]#', '_', $secured_name);
        $secured_name = preg_replace('#__+#', '_', $secured_name);
        $secured_name = trim($secured_name, '_');
        
        $i = 2;
        $complete_path = $this->path . '/' . $secured_name . '.' . $this->ext;
        
        while(file_exists($complete_path))
        {
            $secured_name = $this->name . '_' . $i;
            $complete_path = $this->path . '/' . $secured_name . '.' . $this->ext;
            $i++;
        }

        $this->secured_name = $secured_name;
    }

    public function setFromUrl($url, $path = null, $filename = null)
    {
        if(!empty($path))
        {
            $this->path = $path;
        }

        if(empty($filename))
        {
            $filename = substr($url, strrpos($url, '/') + 1);
        }
        
        $name = substr($filename, 0, strrpos($filename, '.'));
        $ext = substr($filename, strrpos($filename, '.') + 1);

        $this->type = self::getTypeId($ext);
        $path = $this->getUploadRootDir() . (empty($this->path) ? '' : '/'. $this->path);

        if(!is_dir($path))
        {
            mkdir($path);
        }
        
        $name = self::secure($name, $ext, $path);
        $this->filename = $name . '.' . $ext;

        $ch = curl_init($url);

        if(!$ch)
        {
            return false;
        }

        $fp = fopen($path . '/' . $this->filename, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        if(!curl_exec($ch))
        {
            return false;
        }

        curl_close($ch);
        fclose($fp);
        
        if(!empty($patern))
        {
            $resize_path = $this->getImageFile(true, $patern);
            $default_path = $path.'/'.$this->filename;
            
            if($resize_path)
            {   
                unlink($default_path);
                copy($resize_path, $default_path);
            }
        }

        return true;
    }

    private static function readExt($filename)
    {
        return substr($filename, strrpos($filename,'.')+1);
    }

    private static function readName($filename)
    {
        return substr($filename, 0, strrpos($filename,'.'));
    }

    private static function retina($filename)
    {
        return self::readName($filename) . "@2x." . self::readExt($filename);
    }

    /* GETTERS / SETTERS */
    
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set path
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Get path
     *
     * @return string 
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set type
     *
     * @param smallint $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return smallint 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set filename
     *
     * @param string $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Get filename
     *
     * @return string 
     */
    public function getFilename()
    {
        return $this->filename;
    }
}