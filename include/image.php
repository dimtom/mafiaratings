<?php

require_once __DIR__ . '/constants.php';

define('TNAIL_OPTION_SCALE', 0);
define('TNAIL_OPTION_CUT', 1);
define('TNAIL_OPTION_FIT_SCALE', 2);
define('TNAIL_OPTION_FIT', 3);

function image_selected($input_name)
{
	global $_FILES;
	return $_FILES[$input_name]['name'] != '';
}

function is_file_png($filename)
{
	$pos = strrpos($filename, '.'); 
	if ($pos === false)
	{
		throw new Exc(get_label('[0] is not a valid picture file.', $filename));
	} 
	
	$ext = substr($filename, $pos+1);
	if (strcasecmp($ext, 'jpg') == 0 || strcasecmp($ext, 'jpeg') == 0)
	{
		return false;
	}
	
	if (strcasecmp($ext, 'png') == 0)
	{
		return true;
	}
	throw new Exc(get_label('[0] is not a picture file of supported types. We only support jpg and png.', $filename));
}

function generate_thumbnail($img, $t_width, $t_height, $t_option = TNAIL_OPTION_FIT)
{
	$i_width = imagesx($img);
	$i_height = imagesy($img);
	$src_x = 0;
	$src_y = 0;
	$dst_x = 0;
	$dst_y = 0;
	if ($t_width > 0)
	{
		$width = $t_width;
		if ($t_height > 0)
		{
			$height = $t_height;
			switch ($t_option)
			{
				case TNAIL_OPTION_CUT:
					if ($t_width * $i_height < $i_width * $t_height)
					{
						$src_x = $i_width;
						$i_width = floor(($t_width * $i_height) / $t_height);
						$src_x -= $i_width;
						$src_x = floor($src_x / 2);
					}
					else
					{
						$i_height = floor(($t_height * $i_width) / $t_width);
					}
					break;
					
				case TNAIL_OPTION_FIT_SCALE:
					if ($t_width * $i_height < $i_width * $t_height)
					{
						$width = $t_width;
						$height = floor(($t_width * $i_height) / $i_width);
						$dst_y = floor(($t_height - $height) / 2);
					}
					else
					{
						$width = floor(($i_width * $t_height) / $i_height);
						$height = $t_height;
						$dst_x = floor(($t_width - $width) / 2);
					}
					break;
					
				case TNAIL_OPTION_FIT:
					if ($t_height >= $i_height && $t_width >= $i_width)
					{
						$width = $i_width;
						$height = $i_height;
						$dst_x = floor(($t_width - $width) / 2);
						$dst_y = floor(($t_height - $height) / 2);
					}
					else if ($t_width * $i_height < $i_width * $t_height)
					{
						$width = $t_width;
						$height = floor(($t_width * $i_height) / $i_width);
						$dst_y = floor(($t_height - $height) / 2);
					}
					else
					{
						$width = floor(($i_width * $t_height) / $i_height);
						$height = $t_height;
						$dst_x = floor(($t_width - $width) / 2);
					}
					break;
			}
		}
		else
		{
			$t_height = $height = floor(($i_height * $t_width) / $i_width);
		}
	}
	else if ($t_height > 0)
	{
		$t_width = $width = floor(($i_width * $t_height) / $i_height);
		$height = $t_height;
	}
	else
	{
		$t_width = $width = $i_width;
		$t_height = $height = $i_height;
	}
	
	$t_img = imagecreatetruecolor($t_width, $t_height);
	imagealphablending($t_img, false);
	imagesavealpha($t_img, true);
	
	$transp = imagecolorallocatealpha($t_img, 220, 220, 220, 127);
	imagefill($t_img, 0, 0, $transp);
	imagecolortransparent($t_img, $transp);
	
	imagecopyresampled($t_img, $img, $dst_x, $dst_y, $src_x, $src_y, $width, $height, $i_width, $i_height);
	
	return $t_img;
}

function upload_image($input_name, $dst_filename)
{
	global $_FILES;

/*	echo '<pre>';
	print_r($_FILES);
	echo '</pre>';*/
	if (!isset($_FILES[$input_name]))
	{
		throw new FatalExc(get_label('Failed to upload [0].', get_label('file')));
	}
	$file = $_FILES[$input_name];
	
	$src_filename = $file['name'];
	if ($src_filename == '')
	{
		throw new Exc(get_label('Please select a picture to upload.'));
	}
	
	if ($file['error'])
	{
		throw new Exc(get_label('Unable to upload [0]. File is too big.', $src_filename));
	}

	$tmp_filename = $file['tmp_name'];
	if (!is_uploaded_file($tmp_filename))
	{
		throw new Exc(get_label('Failed to upload [0].', $src_filename));
	}
	
	$is_src_png = is_file_png($src_filename);
	$is_dst_png = is_file_png($dst_filename);
	
	// Sometimes we run out of memory. Hopefully running gc before working with memory helps...
	gc_enable(); // Enable Garbage Collector
//	var_dump(gc_collect_cycles()); // returns # of elements cleaned up
	gc_disable(); // Disable Garbage Collector	
	
	if ($is_src_png)
	{
		$img = imagecreatefrompng($tmp_filename);
		if ($is_dst_png)
		{
			copy($tmp_filename, $dst_filename);
		}
		else
		{
			imagejpeg($img, $dst_filename);
		}
	}
	else
	{
		$img = imagecreatefromjpeg($tmp_filename);
		if ($is_dst_png)
		{
			imagepng($img, $dst_filename);
		}
		else
		{
			copy($tmp_filename, $dst_filename);
		}
	}
	
	if (!$img)
	{
		throw new Exc(get_label('Bad image format'));
	}
	
/*	if (!imagefilter($img, IMG_FILTER_GRAYSCALE))
	{
		throw new Exc(get_label('Unable to convert image to grayscale.'));
	}*/
	
	return $img;
}

function build_photo_tnail($dir, $id, $t_option = TNAIL_OPTION_FIT, $img = NULL)
{
	if ($img == NULL)
	{
		$img = imagecreatefromjpeg($dir . $id . '.jpg');
		if (!$img)
		{
			throw new Exc(get_label('Bad image format'));
		}
	}

	$t_img = generate_thumbnail($img, EVENT_PHOTO_WIDTH, 0, $t_option);
	$t_dir = $dir . TNAILS_DIR;
	if (!is_dir($t_dir))
	{
		mkdir($t_dir);
	}
	imagejpeg($t_img, $t_dir . $id . '.jpg');
	imagedestroy($t_img);
}

function upload_photo($input_name, $id, $t_option = TNAIL_OPTION_FIT)
{
	if (!is_dir(PHOTOS_DIR))
	{
		mkdir(PHOTOS_DIR);
	}
	$img = upload_image($input_name, PHOTOS_DIR . $id . '.jpg');
	build_photo_tnail(PHOTOS_DIR, $id, $t_option, $img);
	imagedestroy($img);
}

function build_pic_tnail($dir, $id, $t_option = TNAIL_OPTION_FIT, $img = NULL)
{
	$kill_image = false;
	if ($img == NULL)
	{
		date_default_timezone_set('America/Vancouver');
		$file = $dir . $id . '.png';
		if (!file_exists($file))
		{
			throw new Exc(get_label('Missing file [0]', $file));
		}
		$img = imagecreatefrompng($file);
		if (!$img)
		{
			throw new Exc(get_label('Bad image format [0]', $file));
		}
		$kill_image = true;
	}

	$t_img = generate_thumbnail($img, TNAIL_WIDTH, TNAIL_HEIGHT, $t_option);
	$t_dir = $dir . TNAILS_DIR;
	if (!is_dir($t_dir))
	{
		mkdir($t_dir);
	}
	imagepng($t_img, $t_dir . $id . '.png');
	imagedestroy($t_img);
	
	$t_img = generate_thumbnail($img, ICON_WIDTH, ICON_HEIGHT, $t_option);
	$t_dir = $dir . ICONS_DIR;
	if (!is_dir($t_dir))
	{
		mkdir($t_dir);
	}
	imagepng($t_img, $t_dir . $id . '.png');
	imagedestroy($t_img);
	
	if ($kill_image)
	{
		imagedestroy($img);
	}
}

function upload_pic($input_name, $dir, $id, $t_option = TNAIL_OPTION_FIT)
{
	if (!is_dir($dir))
	{
		mkdir($dir);
	}
	$img = upload_image($input_name, $dir . $id . '.png');
	build_pic_tnail($dir, $id, $t_option, $img);
	imagedestroy($img);
}

function show_photo_thumbnails($page, $condition)
{
	list ($count) = Db::record(get_label('photo'), 'SELECT count(*) FROM photos p WHERE ' . $condition);
	
	show_pages_navigation('album_photos.php?id=' . $album->id, $page, PHOTO_ROW_COUNT * PHOTO_COL_COUNT, $count);
	
	$query = new DbQuery('SELECT p.id FROM photos p WHERE ' . $condition . ' ORDER BY p.id DESC LIMIT ' . ($page * PHOTO_ROW_COUNT * PHOTO_COL_COUNT) . ',' . (PHOTO_ROW_COUNT * PHOTO_COL_COUNT));
	echo '<table class="bordered" width="100%">';
	$col_count = 0;
	$picture_width = CONTENT_WIDTH / PHOTO_COL_COUNT - 20;
	while ($row = $query->next())
	{
		$photo_id = $row[0];
		if ($col_count == 0)
		{
			echo '<tr>';
		}
		
		echo '<td width="' . ($picture_width + 2) . '" align="center" valign="top"><a href="photo.php?id=' . $photo_id . '&page=' . $page . '&bck=1">';
		echo '<img src="' . PHOTOS_DIR . TNAILS_DIR . $photo_id . '.jpg" width="' . $picture_width . '" border="0">';
		echo '</a></td>';
		
		++$col_count;
		if ($col_count == PHOTO_COL_COUNT)
		{
			$col_count = 0;
			echo '</tr>';
		}
	}
	if ($col_count > 0)
	{
		do
		{
			echo '<td width="' . ($picture_width - 2) . '">&nbsp;</td>';
			++$col_count;
			
		} while ($col_count < PHOTO_COL_COUNT);
	}
	echo '</table>';
}

function show_upload_script($code, $id)
{
	$upload_url = 'upload.php?code=' . $code . '&id=' . $id;
	$code = $code . $id;
?>
	<script type="text/javascript" src="js/swfupload.js"></script>
	<script type="text/javascript">
	
	$(function()
	{
		var settings =
		{
			flash_url: "js/swfupload.swf",
			upload_url: "<?php echo $upload_url; ?>",
			post_params:
			{
				"PHPSESSID": "<?php echo session_id(); ?>"
			},
			file_size_limit: "2 MB",
			file_types: "*.jpg; *.jpeg; *.png",
			file_types_description: "<?php echo get_label('Picture Files'); ?>",
			file_upload_limit: 10,
			file_queue_limit: 0,
			debug: false,

			// Button settings
			button_width: "60",
			button_height: "48",
			button_image_url: "images/upload.png",
			button_placeholder_id: "spanButtonPlaceHolder",
			button_action: SWFUpload.BUTTON_ACTION.SELECT_FILE,

			// The event handler functions are defined in handlers.js
			file_queued_handler: fileQueued,
			file_queue_error_handler: fileQueueError,
			file_dialog_complete_handler: fileDialogComplete,
			upload_start_handler: uploadStart,
			upload_error_handler: uploadError,
			upload_success_handler: uploadSuccess
		};

		swfu = new SWFUpload(settings);

		function fileQueued(file)
		{
		}

		function fileQueueError(file, errorCode, message)
		{
			switch (errorCode)
			{
			case SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED:
				dlg.error("<?php echo get_label('Please select one photo.'); ?>");
				break;
			case SWFUpload.QUEUE_ERROR.FILE_EXCEEDS_SIZE_LIMIT:
				dlg.error("<?php echo get_label('File size exeeds 2 Mb. We do not accept big files.'); ?>");
				break;
			case SWFUpload.QUEUE_ERROR.ZERO_BYTE_FILE:
				dlg.error("<?php echo get_label('Cannot upload Zero Byte files.'); ?>");
				break;
			case SWFUpload.QUEUE_ERROR.INVALID_FILETYPE:
				dlg.error("<?php echo get_label('This is not a picture.'); ?>");
				break;
			default:
				dlg.error("<?php echo get_label('Unhandled Error'); ?>");
				break;
			}
		}

		function fileDialogComplete(numFilesSelected, numFilesQueued)
		{
			this.startUpload();
		}

		function uploadStart(file)
		{
			$("#loading").show();
			return true;
		}

		function uploadSuccess(file, serverData)
		{
			$("#loading").hide();
			if (serverData.indexOf('ok', serverData.length - 2) >= 0)
			{
				var d = (new Date()).getTime();
				$("img[code=<?php echo $code; ?>]").each(function()
				{
					$(this).attr('src', $(this).attr('origin') + '?' + d);
				});
			}
			else
			{
				dlg.error(serverData);
			}
		}

		function uploadError(file, errorCode, message)
		{
			$("#loading").hide();
			dlg.error(message);
		}
	});
	</script>
<?php
}

function show_upload_button()
{
	echo '<span id="spanButtonPlaceHolder"></span>';
}

?>