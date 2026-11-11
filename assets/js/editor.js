/* AMIGO! – CKEditor 5 (Open-Source-Version, GPL) für Website-Texte im Adminbereich.
   Wird nur auf den Bearbeiten-Seiten geladen. Alle Dateien liegen lokal (kein CDN). */
import {
  ClassicEditor, Essentials, Autoformat, TextTransformation, PasteFromOffice,
  Paragraph, Heading, Bold, Italic, Underline, Strikethrough, Subscript, Superscript, Code, RemoveFormat,
  Font, Highlight, Alignment, Indent, IndentBlock, BlockQuote, HorizontalLine, CodeBlock,
  Link, AutoLink, List, ListProperties, TodoList,
  Image, ImageCaption, ImageStyle, ImageToolbar, ImageResize, ImageTextAlternative, ImageUpload, LinkImage, SimpleUploadAdapter,
  MediaEmbed, Table, TableToolbar, TableProperties, TableCellProperties, TableCaption, TableColumnResize,
  SpecialCharacters, SpecialCharactersEssentials, FindAndReplace, SourceEditing, ShowBlocks, WordCount,
  Fullscreen, Bookmark, Style, GeneralHtmlSupport
} from '../vendor/ckeditor5-49.1.0/ckeditor5.js';
import de from '../vendor/ckeditor5-49.1.0/translations/de.js';

var RETRO_STYLES = [
  { name: 'Pixel-Überschrift', element: 'h2', classes: ['retro-pixel'] },
  { name: 'Pixel-Text', element: 'p', classes: ['retro-pixel'] },
  { name: 'Info-Kasten', element: 'p', classes: ['retro-box'] },
  { name: 'Neon-Leuchten', element: 'span', classes: ['retro-neon'] },
  { name: 'Arcade-Gelb', element: 'span', classes: ['retro-accent'] }
];

document.querySelectorAll('textarea[data-editor]').forEach(function (area) {
  var status = area.parentNode.querySelector('[data-editor-count]');
  ClassicEditor.create(area, {
    licenseKey: 'GPL',
    language: 'de',
    translations: [de],
    plugins: [
      Essentials, Autoformat, TextTransformation, PasteFromOffice,
      Paragraph, Heading, Bold, Italic, Underline, Strikethrough, Subscript, Superscript, Code, RemoveFormat,
      Font, Highlight, Alignment, Indent, IndentBlock, BlockQuote, HorizontalLine, CodeBlock,
      Link, AutoLink, List, ListProperties, TodoList,
      Image, ImageCaption, ImageStyle, ImageToolbar, ImageResize, ImageTextAlternative, ImageUpload, LinkImage, SimpleUploadAdapter,
      MediaEmbed, Table, TableToolbar, TableProperties, TableCellProperties, TableCaption, TableColumnResize,
      SpecialCharacters, SpecialCharactersEssentials, FindAndReplace, SourceEditing, ShowBlocks, WordCount,
      Fullscreen, Bookmark, Style, GeneralHtmlSupport
    ],
    toolbar: {
      items: [
        'undo', 'redo', '|', 'heading', 'style', '|',
        'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'code', 'removeFormat', '|',
        'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
        'alignment', 'bulletedList', 'numberedList', 'todoList', 'outdent', 'indent', '|',
        'link', 'bookmark', 'uploadImage', 'insertTable', 'mediaEmbed', 'blockQuote', 'codeBlock', 'horizontalLine', 'specialCharacters', '|',
        'findAndReplace', 'showBlocks', 'sourceEditing', 'fullscreen'
      ],
      shouldNotGroupWhenFull: false
    },
    heading: {
      options: [
        { model: 'paragraph', title: 'Absatz', class: 'ck-heading_paragraph' },
        { model: 'heading2', view: 'h2', title: 'Überschrift 1', class: 'ck-heading_heading2' },
        { model: 'heading3', view: 'h3', title: 'Überschrift 2', class: 'ck-heading_heading3' },
        { model: 'heading4', view: 'h4', title: 'Überschrift 3', class: 'ck-heading_heading4' }
      ]
    },
    style: { definitions: RETRO_STYLES },
    // Nur die Klassen der Retro-Stile zulassen – sonst kein beliebiges HTML.
    htmlSupport: { allow: [{ name: /^(p|h2|span)$/, classes: ['retro-pixel', 'retro-box', 'retro-neon', 'retro-accent'] }] },
    fontFamily: {
      options: ['default', '"Press Start 2P", monospace', 'Arial, Helvetica, sans-serif', 'Georgia, serif', '"Courier New", Courier, monospace', 'Verdana, Geneva, sans-serif']
    },
    fontSize: { options: ['tiny', 'small', 'default', 'big', 'huge'] },
    link: {
      addTargetToExternalLinks: true,
      defaultProtocol: 'https://'
    },
    list: { properties: { styles: true, startIndex: true, reversed: true } },
    image: {
      toolbar: ['imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'toggleImageCaption', 'imageTextAlternative', 'linkImage', '|', 'resizeImage'],
      upload: { types: ['jpeg', 'png', 'gif', 'webp'] }
    },
    table: {
      contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties', 'toggleTableCaption']
    },
    // Videos werden auf der Website als Link angezeigt (keine eingebetteten Fremd-Player = kein Tracking).
    mediaEmbed: { previewsInData: false },
    simpleUpload: {
      uploadUrl: area.getAttribute('data-upload'),
      headers: { 'X-CSRF-Token': area.getAttribute('data-csrf') }
    },
    wordCount: {
      onUpdate: function (stats) {
        if (status) status.textContent = stats.words + ' Wörter · ' + stats.characters + ' Zeichen';
      }
    }
  }).then(function (editor) {
    // Inhalte im Editor so darstellen wie später auf der Website
    editor.editing.view.change(function (writer) {
      writer.addClass('prose', editor.editing.view.document.getRoot());
    });
  }).catch(function (err) {
    area.hidden = false;
    if (status) status.textContent = 'Editor konnte nicht geladen werden – einfaches Textfeld (HTML) wird verwendet.';
    console.error(err);
  });
});
