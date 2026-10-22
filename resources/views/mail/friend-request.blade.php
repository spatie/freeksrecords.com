<x-mail::layout>
<x-slot:head>
<style>
@media only screen and (max-width: 600px) {
    .body { padding: 0 !important; }
    .request-masthead, .request-poster, .request-copy, .request-signoff { padding-left: 24px !important; padding-right: 24px !important; }
    .request-good, .request-company { font-size: 52px !important; letter-spacing: -3px !important; }
    .request-vinyl, .request-vinyl-cell { width: 72px !important; }
}
</style>
</x-slot:head>
<x-mail::friend-request :sender="$sender" :links="$links" />
</x-mail::layout>
