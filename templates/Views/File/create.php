<?php

use App\View;
?>
    <div>
        <div class="max-w-md mb-8">
            <form class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4" method="post" action="/file-manager" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="file">File</label>
                    <input class="focus:outline-none focus:ring-2 focus:ring-indigo-700 shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight bg-white focus:outline-none focus:shadow-outline"
                           id="file" name="file" type="file"
                    >
                </div>
                <div class="flex items-center justify-between">
                    <button class="focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-700 bg-indigo-500 hover:bg-indigo-700 text-white font-bold mr-8 py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                            type="submit">
                        Добавить
                    </button>
            </form>
        </div>

        <div class="flex justify-center">
            <div class="hidden alert mt-8 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"
                 role="alert">
                <strong class="font-bold">Ошибка!</strong>
                <span class="alert-message inline"></span>
            </div>
        </div>
    </div>

<?php
View::setJs('/js/files/create.js');
