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
 */
abstract class File
{
    const TYPE_FILE = 0;
    const TYPE_IMAGE = 1;

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
     * @var smallint $type
     *
     * @ORM\Column(name="type", type="smallint")
     */
    protected $type;
    
    public $file;
    
    protected $paterns;
    
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
    
    public function selectType($type)
    {
        $this->type = self::getTypeId($type);
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
    
    
    /* Methods */
    
    public static function getTypeName($type_id)
    {
        switch ($type_id)
        {
            case self::TYPE_IMAGE:   return 'image';
            break;
        
            default:   return 'file';
            break;
        }
    }
    
    public static function getTypeId($type_name)
    {
        switch (strtolower($type_name))
        {
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'image':   
                return self::TYPE_IMAGE;
            break;
            default:   
                return self::TYPE_FILE;
            break;
        }
    }
    
    public function getAbsolutePath()
    {
        return $this->getUploadRootDir().(empty($this->path) ? '' : '/'.$this->path).'/'.$this->filename;
    }

    public function getWebPath()
    {
        return '/'.$this->getUploadDir().(empty($this->path) ? '' : '/'.$this->path).'/'.$this->filename;
    }

    protected function getUploadRootDir()
    {
        // the absolute directory path where uploaded documents should be saved
        return __DIR__.'/../../../../web/'.$this->getUploadDir();
    }

    protected function getUploadDir()
    {
        // get rid of the __DIR__ so it doesn't screw when displaying uploaded doc/image in the view.
        return 'uploads';
    }
    
    protected function getPaternOptions($patern)
    {
        if(array_key_exists($patern, $this->paterns))
        {
            return $this->paterns[$patern];
        }
        
        return null;
    }


    /**
     * @ORM\PostPersist()
     * @ORM\PostUpdate()
     */
    public function upload($path = null, $name = null, $patern = null)
    {
        // the file property can be empty if the field is not required
        if (null === $this->file) {
            return;
        }
        
        if(!empty($path))
        {
            $this->path = $path;
        }
        
        $ext = strtolower($this->file->guessExtension());
        if($ext == 'jpeg'){ $ext = 'jpg'; }

        $this->type = self::getTypeId($ext);
        $path = $this->getUploadRootDir().(empty($this->path) ? '' : '/'.$this->path);
        
        $name = empty($name) ? self::getTypeName($this->type).'_'.uniqid() : self::secure($name, $ext, $path);
        $this->filename = $name.'.'.$ext;
        
        // move takes the target directory and then the target filename to move to
        $this->file->move($path, $this->filename);

        // clean up the file property as you won't need it anymore
        $this->file = null;
        
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
    }

    /**
     * @ORM\PostRemove()
     */
    public function removeUpload()
    {
        if($this->filename)
        {
            $file = $this->getAbsolutePath();

            if (file_exists($file)) 
            {
                unlink($file);
            }
        }
    }
    
    public function __toString()
    {
        return $this->getWebPath();
    }
    
    public function display($options = array())
    {
        if(is_string($options))
        {
            $options = array('patern' => $options);
        }
        
        $html = "";
        
        if(!array_key_exists('title', $options) && !empty($this->name))
        {
            $options['title'] =  $this->name;
        }
        if(!array_key_exists('alt', $options))
        {
            $options['alt'] =  empty($this->name) ? $this->filename : $this->name;
        }
        
        switch($this->type)
        {
            case self::TYPE_IMAGE :
                $patern = null;
                $attr = '';
                
                if(array_key_exists('patern', $options))
                {
                    $patern = $options['patern'];
                    $patern_options = $this->getPaternOptions($patern);
                    
                    if($patern_options)
                    {
                       $params = array('width', 'height');
                       
                       foreach($params as $p)
                       {
                           $attr .= (array_key_exists($p, $patern_options) ? ' '.$p.'="'.$patern_options[$p].'"' : ''); 
                       }
                    }
                    unset($options['patern']);
                }
                
                $path = $this->getImageFile(false, $patern);
                
                if(array_key_exists('toggle', $options))
                {
                    $toggle = $options['toggle'];
                    $toggle_path = $this->getImageFile(false, $toggle);
                    $attr .= ' data-toggle="'.$toggle_path.'"';
                    unset($options['toggle']);
                }
                
                foreach($options as $key => $val)
                {
                    $attr .= ' '.$key.'="'.$val.'"'; 
                }
                
                $html = '<img src="'.$path.'" '.$attr.' />';
            break;
        }
        
        return $html;
    }
    
    public function getImageContent($patern, $response)
    {
        $file = $this->getImageFile(true, $patern);
        
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
        
        $original_path = $this->getUploadRootDir().'/'.$this->path;
        $patern_path = $original_path.(empty($patern) ? '' : '/'.$patern);
        $file = $patern_path.'/'.$filename;
        $ext = substr($filename, strrpos($filename,'.')+1);
        $create = false;
        
        if(!file_exists($original_path.'/'.$filename))
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
            $loadfile = $original_path.'/'.$filename;
            
            switch($ext)
            {
                case 'jpg':                   
                    $image = $this::processImage($loadfile, imagecreatefromjpeg($loadfile), $this->getPaternOptions($patern));
                    imagejpeg($image, $file, 100);
                break;
                case 'png':      
                    $image = $this::processImage($loadfile, imagecreatefrompng($loadfile), $this->getPaternOptions($patern), true);
                    imagepng($image, $file, 9);
                break;
                case 'gif':  
                    $image = $this::processImage($loadfile, imagecreatefromgif($loadfile), $this->getPaternOptions($patern));
                    imagegif($image, $file);
                break;
            }
        }
        
        return $absolute ? $file : '/'.$this->getUploadDir().(empty($this->path) ? '' : '/'.$this->path).(empty($patern) ? '' : '/'.$patern).'/'.$filename;
    }
    
    static public function processImage($file, $image, $options, $alpha = false)
    {  
        $dst_x = 0;
        $dst_y = 0;
        $src_x = 0;
        $src_y = 0;
        
        $dst_width = array_key_exists('width', $options) ? $options['width'] : null;
        $dst_height = array_key_exists('height', $options) ? $options['height'] : null;
        $method = array_key_exists('method', $options) ? $options['method'] : 'fill';
        
        $datas = getimagesize($file);
        $src_width = $datas[0];
        $src_height = $datas[1];
        
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
                    //var_dump($src_x);
                    //exit();
                }
                else
                {
                    $new_width = $dst_width;
                    $new_height = floor(($dst_width * $src_height) / $src_width);
                    $dst_y = floor(($dst_height - $new_height)/2);
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
                else
                {
                    $new_width = floor(($dst_height * $src_width) / $src_height);
                    $new_height = $dst_height;
                    $dst_x = floor(($dst_width - $new_width)/2);
                }
            }
            else
            {
                $new_width = $dst_width;
                $new_height = $dst_height;
            }
        }
        elseif($dst_width === null)
        {
            $new_height = $dst_height;
            $new_width = floor(($dst_height * $src_width) / $src_height);
            $dst_width = $new_width;
        }
        elseif($dst_height === null)
        {
            $new_width = $dst_width;
            $new_height = floor(($dst_width * $src_height) / $src_width);
            $dst_height = $new_height;
        }
        
        $thumb = imagecreatetruecolor($dst_width, $dst_height);
        
        if($alpha){
            imagealphablending($thumb, false);
            imagesavealpha($thumb,true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefilledrectangle($thumb, 0, 0, $dst_width, $dst_height, $transparent);
        }
        
        imagecopyresampled($thumb, $image, $dst_x, $dst_y, $src_x, $src_y, $new_width, $new_height, $src_width, $src_height);
        
        unset($image);
        
        return $thumb;
    }
    
    private static function secure($name, $ext, $path)
    {
        $secured_name = strtolower($name);
        
        $secured_name = preg_replace('#[^a-z0-9_.]#', '_', $secured_name);
        $secured_name = preg_replace('#__+#', '_', $secured_name);
        $secured_name = trim($secured_name, '_');
        
        $complete_path = $path.'/'.$secured_name.'.'.$ext;
        $i = 2;
        
        while(file_exists($complete_path))
        {
            $secured_name = $name.'_'.$i;
            $complete_path = $path.'/'.$secured_name.'.'.$ext;
            $i++;
        }
        
        return $secured_name;
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
        $this->filename = $name.'.'.$ext;

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
}