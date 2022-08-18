# bitrix_code_example

Пара примеров моего кода на Битрикс.

## [ajax_catalog_csv.php](/ajax_catalog_csv.php)

Скрипт формирования `CSV` файла товаров из раздела, в котором находиться пользователь, нажавший на кнопку "скачать".

Так как свойства товара не менялись и заказчик просил сделать это быстро, то просто вписал необходимые свойства прямо в скрипт...

## [put_photos_sections.php](/put_photos_sections.php)

Простенький скрипт для проброса фото разделов из старого каталога в новый.

Спустя время преобразилась структура каталога (корневые разделы стали отдельными инфоблоками) и сменились наименования разделов, из-за этого пришлось вручную прописать `switch` символьных кодов, которые формируются из транслитерации названия.
