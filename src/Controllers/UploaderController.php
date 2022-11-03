<?php

namespace Alexandrnya\Laravel\Files\Controllers;

use Alexandrnya\Laravel\Files\Models\TempFileModel;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class UploaderController extends Controller
{
    public function upload(Request $request)
    {
        return TempFileModel::store($request->file('file'), ['uuid' => session()->getId()]);
    }

    public function show($id, $name)
    {
        return TempFileModel::where([
            'id' => $id,
            'uuid' => session()->getId(),
            'name' => $name,
        ])->firstOrFail()->response();
    }
}
