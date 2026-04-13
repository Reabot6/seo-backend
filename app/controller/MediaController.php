<?php
namespace app\controller;

use think\facade\Db;
use think\Request;

class MediaController
{
    // GET /api/media
    public function index(Request $request)
    {
        $page   = $request->get('page', 1);
        $limit  = $request->get('limit', 20);
        $search = $request->get('search', '');
        $type   = $request->get('type', '');

        $query = Db::table('media');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereLike('filename', "%{$search}%")
                  ->whereOrLike('tags', "%{$search}%");
            });
        }

        if ($type) $query->where('type', $type);

        $total = $query->count();
        $media = $query->page($page, $limit)
                       ->order('created_at', 'desc')
                       ->select()->toArray();

        return json([
            'status' => 'success',
            'data'   => $media,
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
        ]);
    }

    // POST /api/media/upload
    public function upload(Request $request)
    {
        $file = $request->file('file');

        if (!$file) {
            return json(['status' => 'error', 'message' => 'No file provided'], 400);
        }

        $tags     = $request->post('tags', '');
        $type     = $request->post('type', 'image');

        // Validate file type
        $allowedImages = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedVideos = ['mp4', 'mov', 'avi', 'webm'];
        $allowed       = array_merge($allowedImages, $allowedVideos);

        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, $allowed)) {
            return json(['status' => 'error', 'message' => 'File type not allowed'], 400);
        }

        // Determine type from extension
        if (in_array($ext, $allowedImages)) $type = 'image';
        if (in_array($ext, $allowedVideos)) $type = 'video';

        // Save file
        $uploadPath = app()->getRootPath() . 'public/uploads/';
        if (!is_dir($uploadPath)) mkdir($uploadPath, 0755, true);

        $filename = date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $file->move($uploadPath, $filename);

        $filepath = '/uploads/' . $filename;
        $size     = round(filesize($uploadPath . $filename) / 1024) . ' KB';

        $id = Db::table('media')->insertGetId([
            'filename'   => $file->getOriginalName(),
            'filepath'   => $filepath,
            'type'       => $type,
            'size'       => $size,
            'tags'       => $tags,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return json([
            'status'  => 'success',
            'message' => 'File uploaded successfully',
            'id'      => $id,
            'filepath'=> $filepath,
        ]);
    }

    // DELETE /api/media/:id
    public function destroy($id)
    {
        $media = Db::table('media')->where('id', $id)->find();
        if (!$media) {
            return json(['status' => 'error', 'message' => 'Media not found'], 404);
        }

        // Delete file from disk
        $filePath = app()->getRootPath() . 'public' . $media['filepath'];
        if (file_exists($filePath)) unlink($filePath);

        Db::table('media')->where('id', $id)->delete();
        Db::table('article_media')->where('media_id', $id)->delete();

        return json(['status' => 'success', 'message' => 'Media deleted successfully']);
    }

    // GET /api/media/stats
    public function stats()
    {
        $totalImages = Db::table('media')->where('type', 'image')->count();
        $totalVideos = Db::table('media')->where('type', 'video')->count();
        $uniqueTags  = Db::table('media')->column('tags');

        $tags = [];
        foreach ($uniqueTags as $tagStr) {
            if ($tagStr) {
                foreach (explode(',', $tagStr) as $tag) {
                    $tags[] = trim($tag);
                }
            }
        }
        $uniqueTagCount = count(array_unique(array_filter($tags)));

        return json([
            'status' => 'success',
            'data'   => [
                'total_images' => $totalImages,
                'total_videos' => $totalVideos,
                'unique_tags'  => $uniqueTagCount,
            ],
        ]);
    }
}