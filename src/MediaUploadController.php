<?php namespace Triasrahman\MediaUpload;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Input;
use Intervention\Image\Facades\Image;

class MediaUploadController extends \Illuminate\Routing\Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request, $type = 'default')
    {
        // Get the configuration
        $config = Config::get('media-upload');

        $configType = Config::get('media-upload.types.'.$type);

        if ( ! $configType )
        {
            return [ 'error' => 'type-not-found' ];
        }

        // Check if file is uploaded
        if ( !Input::hasFile('file') )
        {
            return [ 'error' => 'file-not-found' ];
        }

        $file = Input::file('file');

        // get file size in Bytes
        $file_size = $file->getSize();

        // Check the file size
        if ( $file_size > $config['max_size'] * 1024 || ( isset($configType['max_size']) && $file_size > $configType['max_size'] * 1024 ) )
        {
            return [ 'error' => 'limit-size' ];
        }

        // get the extension
        $ext = strtolower( $file->getClientOriginalExtension() );

        // checking file format
        $format = $this->getFileFormat($ext);

        // TODO: check file format
        if( isset($configType['format']) && ! in_array($format, explode('|', $configType['format'])) )
        {
            return [ 'error' => 'invalid-format' ];
        }

        // saving file
        // saving file
        $move_path              = base_path() . '/public/uploads/';
        $hash                   = md5(config('app.salt') . ':' . date('U') . str_random(4));
        $filename_first_dir     = substr($hash, 0, 4);
        $filename_second_dir    = substr($hash, 4, 4);
        $filename               = substr($hash, 8);
        
        $file->move($move_path . $type . '/' . $filename_first_dir . '/' . $filename_second_dir, $filename . '.' . $ext);

        $file_path = $type . '/' . $filename_first_dir . '/' . $filename_second_dir . '/' . $filename . '.' . $ext;
        
        if ( $format == 'image' && isset($config['types'][$type]['image']) && count($config['types'][$type]['image']) )
        {

            $img = Image::make($move_path.'/'.$file_path);

            foreach($config['types'][$type]['image'] as $task => $params)
            {
                switch($task) {
                    case 'resize':
                        if ($params[0] && $params[1]) {
                            $img->resize($params[0], $params[1]);
                        } else {
                            if ($img->width() > $params[0])  {
                                $img->resize($params[0], null, function ($constraint) {
                                    $constraint->aspectRatio();
                                });
                            } else {
                                $img->resize($img->width(), null, function ($constraint) {
                                    $constraint->aspectRatio();
                                });
                            }
                        }
                        break;
                    case 'fit':
                        $img->fit($params[0], $params[1]);
                        break;
                    case 'crop':
                        $img->crop($params[0], $params[1]);
                        break;
                    case 'thumbs':
                        $img->save();

                        foreach($params as $name => $sizes) {
                            
                            $img->backup();

                            $thumb_path = $config['dir'].'/'.$filename.'-'.$name.'.'.$ext;

                            $img->fit($sizes[0], $sizes[1])->save($thumb_path);

                            $img->reset();
                        }
                        break;
                }
            }

            $img->save();
        }

        return [
            'original' => [
                'name' => $file->getClientOriginalName(),
                'size' => $file_size,
            ],
            'ext' => $ext,
            'format' => $format,
            // 'image' => [
            //     'size' =>$img->getSize(),
            // ],
            'name' => $filename,
            'path' => $file_path,
        ];

    }

    protected function getFileFormat($ext) {
        if ( preg_match('/(jpg|jpeg|gif|png)/', $ext) )
        {
            return 'image';
        }
        elseif( preg_match( '/(mp3|wav|ogg)/', $ext) )
        {
            return 'audio';
        }
        elseif( preg_match( '/(mp4|wmv|flv)/', $ext) )
        {
            return 'video';
        }
        elseif( preg_match('/txt/', $ext) )
        {
            return 'text';
        }
        
        return 'other';
    }

}
