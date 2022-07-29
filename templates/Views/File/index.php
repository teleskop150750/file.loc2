<?php

use App\View;
use FileManager\Entity\FileEntity;

/** @var array $data */
/** @var FileEntity[] $files */
$files = $data['files'];

if (count($files)) : ?>
    <div class="max-w-5xl">
        <div class="pb-4 overflow-x-auto">
            <div class="inline-block min-w-full shadow rounded-lg overflow-hidden">
                <table class="min-w-full leading-normal">
                    <thead>
                    <tr>
                        <th class="whitespace-nowrap px-5 py-3 border-b-2 border-gray-200 bg-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Name
                        </th>
                        <th class="whitespace-nowrap px-5 py-3 border-b-2 border-gray-200 bg-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="whitespace-nowrap px-5 py-3 border-b-2 border-gray-200 bg-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Download
                        </th>
                        <th class="whitespace-nowrap px-5 py-3 border-b-2 border-gray-200 bg-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Delete
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($files as $file) : ?>
                        <tr>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <div class="flex items-center">
                                    <p class="ml-3 text-gray-900 whitespace-nowrap">
                                        <a href="<?= $file->getUrl() ?>">
                                            <?= $file->getOriginName() ?>
                                        </a>
                                    </p>
                                </div>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <div class="flex items-center">
                                    <p class="text-gray-900 whitespace-nowrap">
                                        <?= $file->getCreatedAt() ?>
                                    </p>
                                </div>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <a href="/file-manager/?get_file=<?= $file->getId() ?>"
                                   class="relative inline-block px-3 py-1 font-semibold text-green-900 leading-tight">
                                    <span class="absolute inset-0 bg-green-200 opacity-50 rounded-full"></span>
                                    <span class="relative">Download</span>
                                </a>
                            </td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                <button data-id="<?= $file->getId() ?>"
                                        class="delete-item relative inline-block px-3 py-1 font-semibold text-red-900 leading-tight">
                                    <span class="absolute inset-0 bg-red-200 opacity-50 rounded-full"></span>
                                    <span class="relative">Delete</span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else : ?>
    <div class="mx-auto justify-center items-center flex-col mb-10">
        <p class="text-xl sm:text-2xl text-center text-gray-800 font-bold leading-10">
            Файлов нет
        </p>
    </div>
<?php endif; ?>


<?php
View::setJs('/js/files/index.js');
