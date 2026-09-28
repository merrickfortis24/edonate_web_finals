<script>
window.eDonateFacilityStatus = function(value) {
    const active = String(value ?? 'active').trim().toLowerCase() === 'active';
    return `<span class="badge rounded-pill d-inline-flex align-items-center gap-1 ${active ? 'text-bg-success' : 'text-bg-secondary'}"><i class="bi ${active ? 'bi-check-circle-fill' : 'bi-slash-circle'}" aria-hidden="true"></i><span>${active ? 'Active' : 'Inactive'}</span></span>`;
};
</script>
