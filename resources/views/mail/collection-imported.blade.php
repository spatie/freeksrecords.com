<x-mail::layout>
<x-slot:head>
<style>
@media only screen and (max-width: 600px) {
    .body { padding: 0 !important; }
    .request-masthead, .request-poster, .request-copy { padding-left: 24px !important; padding-right: 24px !important; }
    .request-vinyl, .request-vinyl-cell { width: 104px !important; }
    .import-quote { font-size: 26px !important; letter-spacing: -0.75px !important; }
}
</style>
</x-slot:head>
<x-mail::collection-imported :collector="$collector" :record-count="$recordCount" :url="$url" />
</x-mail::layout>
