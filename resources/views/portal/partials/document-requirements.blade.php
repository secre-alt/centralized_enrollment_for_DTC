@if (in_array($documentType, ['tor', 'transcript'], true))
    Clearance for a first request or transfer purposes, and two 2×2 photos with a white background or in academic gown.
@elseif (in_array($documentType, ['diploma', 'certification'], true))
    Clearance for a first request.
@else
    Original and photocopy of the documents to be certified.
@endif
