<x-mail::layout>
<x-slot:head>
<style>
@media only screen and (max-width: 600px) {
    .body { padding: 0 !important; }
    .digest-masthead, .digest-poster, .digest-row, .digest-signoff, .digest-footer { padding-left: 20px !important; padding-right: 20px !important; }
    .digest-heading { font-size: 48px !important; letter-spacing: -2px !important; }
    .digest-art { width: 120px !important; padding-right: 16px !important; }
    .digest-cover, .digest-missing { width: 104px !important; height: 104px !important; }
    .digest-title { font-size: 25px !important; }
}
</style>
</x-slot:head>
<x-mail::friends-digest :records="$records" :covers="$covers" :web-preview="$webPreview ?? false" :mail-message="$message" />
</x-mail::layout>
