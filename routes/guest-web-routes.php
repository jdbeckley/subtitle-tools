<?php

Route::get('st-login',   ['uses' => 'LoginController@showLoginForm', 'as' => 'login'])->middleware('guest');
Route::post('st-login',  ['uses' => 'LoginController@login',         'as' => 'login.post']);
Route::post('st-logout', ['uses' => 'LoginController@logout',        'as' => 'logout']);

Route::view('/', 'home')->name('home');

Route::view('/how-to-fix-vlc-subtitles-displaying-as-boxes', 'blogs.fix-vlc-subtitle-boxes')->name('blog.vlcSubtitleBoxes');

Route::get('/contact')->uses('ContactController@index')->name('contact');
Route::post('/contact')->uses('ContactController@post')->name('contact.post');

Route::get('/stats')->uses('StatsController@index')->name('stats');

Route::post('/file-group-archive/{urlKey}')->uses('DownloadController@fileGroupArchive')->name('fileGroupArchiveDownload');
Route::get('/file-group-archive/{urlKey}', function ($urlKey) {
    $fileGroup = \App\Models\FileGroup::where('url_key', $urlKey)->firstOrFail();

    return redirect($fileGroup->resultRoute);
});

Route::prefix('convert-sub-idx-to-srt-online')->group(function () {
    Route::get('/',                  ['uses' => 'SubIdxController@index',       'as' => 'subIdx']);
    Route::post('/',                 ['uses' => 'SubIdxController@post',        'as' => 'subIdx.post'])->middleware('swap-sub-and-idx');
    Route::get('/{pageId}',          ['uses' => 'SubIdxController@detail',      'as' => 'subIdx.show']);
    Route::post('/{pageId}/{index}', ['uses' => 'SubIdxController@downloadSrt', 'as' => 'subIdx.download']);

    Route::get('/{pageId}/{index}', function ($pageId, $index) {
        return redirect()->route('subIdx.show', $pageId);
    });
});

/**
 * When adding a new FileGroupTool route, also add it to "config/st.php".
 */
Route::fileGroupTool('convertToSrt',       'ConvertToSrtController',       'convert-to-srt-online');
Route::fileGroupTool('convertToVtt',       'ConvertToVttController',       'convert-to-vtt-online');
Route::fileGroupTool('cleanSrt',           'CleanSrtController',           'srt-cleaner');
Route::fileGroupTool('shift',              'ShiftController',              'subtitle-sync-shifter');
Route::fileGroupTool('shiftPartial',       'ShiftPartialController',       'partial-subtitle-sync-shifter');
Route::fileGroupTool('convertToUtf8',      'ConvertToUtf8Controller',      'convert-text-files-to-utf8-online');
Route::fileGroupTool('pinyin',             'PinyinController',             'make-chinese-pinyin-subtitles');
Route::fileGroupTool('convertToPlainText', 'ConvertToPlainTextController', 'convert-subtitles-to-plain-text-online');
Route::fileGroupTool('merge',              'MergeController',              'merge-subtitles-online');


Route::prefix('convert-sup-to-srt-online')->group(function () {
    Route::get('/',                   ['uses' => 'SupController@index',    'as' => 'sup']);
    Route::post('/',                  ['uses' => 'SupController@post',     'as' => 'sup.post']);
    Route::get('/{urlKey}',           ['uses' => 'SupController@show',     'as' => 'sup.show']);
    Route::post('/{urlKey}/download', ['uses' => 'SupController@download', 'as' => 'sup.show.download']);

    Route::get('/{urlKey}/download', function ($urlKey) {
        return redirect()->route('sup.show', $urlKey);
    });
});
