@push('styles')
<style>
.income-upload-preview { padding: .75rem; border: 1px solid var(--vf-light); border-radius: .75rem; background: var(--vf-background); max-width: 34rem; }
.income-upload-preview[hidden], .income-upload-preview [hidden] { display: none !important; }
.income-preview-button { display: block; padding: 0; border: 0; background: transparent; text-align: left; cursor: zoom-in; }
.income-preview-button img { display: block; max-width: 100%; max-height: 200px; border-radius: .5rem; object-fit: contain; }
.income-upload-preview iframe { display: block; width: 100%; height: 220px; border: 1px solid var(--vf-light); border-radius: .5rem; background: white; }
.income-quality-dialog { max-width: min(94vw, 1000px); max-height: 92vh; padding: 1rem; border: 1px solid var(--vf-light); border-radius: 1rem; color: var(--vf-navy); background: white; }
.income-quality-dialog::backdrop { background: rgba(6,43,128,.5); backdrop-filter: blur(4px); }
.income-quality-dialog img { display: block; max-width: 100%; max-height: 75vh; object-fit: contain; margin: auto; }
.income-quality-dialog iframe { width: min(85vw, 900px); height: 75vh; border: 0; }
.income-quality-dialog [hidden] { display: none !important; }
</style>
@endpush
<dialog class="income-quality-dialog" id="incomeProofDialog" aria-labelledby="incomeProofDialogTitle">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-2"><h2 class="h6 mb-0" id="incomeProofDialogTitle">Proof of Income</h2><button type="button" class="btn-close" aria-label="Close preview" data-close-income-dialog></button></div>
    <img alt="Enlarged proof of income" hidden><iframe title="Enlarged proof of income PDF" hidden></iframe>
</dialog>
@push('scripts')
<script>
(() => {
    const dialog = document.getElementById('incomeProofDialog');
    const dialogImage = dialog?.querySelector('img');
    const dialogPdf = dialog?.querySelector('iframe');
    dialog?.querySelector('[data-close-income-dialog]')?.addEventListener('click', () => dialog.close());
    dialog?.addEventListener('keydown', (event) => { if (event.key === 'Escape') event.stopPropagation(); });
    dialog?.addEventListener('close', () => { dialogImage.removeAttribute('src'); dialogPdf.removeAttribute('src'); });
    function enlarge(url, pdf = false) {
        if (!dialog || !url) return;
        const activeModal = document.querySelector('.modal.show');
        (activeModal || document.body).append(dialog);
        dialogImage.hidden = pdf; dialogPdf.hidden = !pdf;
        (pdf ? dialogPdf : dialogImage).src = url;
        dialog.showModal();
    }
    async function assess(image) {
      if (!image.complete) await new Promise((resolve,reject)=>{image.addEventListener('load',resolve,{once:true});image.addEventListener('error',reject,{once:true})});
      const W=image.naturalWidth,H=image.naturalHeight;if(!W||!H)throw new Error('The image is unreadable or corrupted.');
      // Allow the checking indicator to paint before sampling original-resolution tiles.
      await new Promise(resolve => setTimeout(resolve, 0));
      const tile=128, cols=Math.ceil(W/tile),rows=Math.ceil(H/tile), stride=Math.max(1,Math.ceil(Math.sqrt(cols*rows/400)));
      const canvas=document.createElement('canvas');canvas.width=tile;canvas.height=tile;const ctx=canvas.getContext('2d',{willReadFrequently:true});if(!ctx)throw new Error('Image analysis is unavailable.');
      const regions=[];let globalL=0,globalN=0;
      for(let ty=0;ty<rows;ty+=stride)for(let tx=0;tx<cols;tx+=stride){
        const sx=tx*tile,sy=ty*tile,w=Math.min(tile,W-sx),h=Math.min(tile,H-sy);if(w<24||h<24)continue;
        ctx.clearRect(0,0,tile,tile);ctx.drawImage(image,sx,sy,w,h,0,0,w,h);const data=ctx.getImageData(0,0,w,h).data,g=new Float32Array(w*h),sat=new Uint8Array(w*h),hist=new Uint32Array(256);let sum=0;
        for(let i=0;i<g.length;i++){let k=i*4,a=data[k+3]/255,r=data[k]*a+255*(1-a),gr=data[k+1]*a+255*(1-a),b=data[k+2]*a+255*(1-a);let v=.299*r+.587*gr+.114*b;g[i]=v;hist[Math.round(v)]++;sum+=v;sat[i]=(Math.max(r,gr,b)-Math.min(r,gr,b)>75)?1:0;}
        globalL+=sum;globalN+=g.length;let lo=0,hi=255,cum=0;for(let k=0;k<256;k++){cum+=hist[k];if(cum>=g.length*.01){lo=k;break}}cum=0;for(let k=255;k>=0;k--){cum+=hist[k];if(cum>=g.length*.01){hi=k;break}}
        let n=0,strong=0,gradSum=0,lapSum=0,nx=0,ny=0,satEdge=0;const cells=new Set(),xbins=new Set(),ybins=new Set();
        for(let y=1;y<h-1;y+=2)for(let x=1;x<w-1;x+=2){let i=y*w+x,dx=Math.abs(g[i+1]-g[i-1])/2,dy=Math.abs(g[i+w]-g[i-w])/2,grad=Math.max(dx,dy);if(grad<7)continue;n++;gradSum+=grad;lapSum+=Math.abs(4*g[i]-g[i-1]-g[i+1]-g[i-w]-g[i+w]);if(grad>=35)strong++;if(dx>=7)nx++;if(dy>=7)ny++;satEdge+=sat[i];cells.add((Math.floor(y/16)*8)+Math.floor(x/16));xbins.add(Math.floor(x/8));ybins.add(Math.floor(y/8));}
        const pixels=Math.ceil((w-2)/2)*Math.ceil((h-2)/2),density=n/pixels,axis=Math.min(nx,ny)/Math.max(1,Math.max(nx,ny));
        if(n>=28 && density>.004 && density<.38 && cells.size>=3 && xbins.size>=3 && ybins.size>=2 && axis>.12 && satEdge/n<.7 && hi-lo>=12) regions.push({x:sx,y:sy,n,density,contrast:hi-lo,gradient:gradSum/n,strong:strong/n,lap:lapSum/n,mean:sum/g.length,axis});
      }
      const median=(arr)=>{arr.sort((a,b)=>a-b);return arr[Math.floor(arr.length/2)]||0};
      // Only tiles with distributed, two-directional, mostly neutral edges enter the score.
      // A decorative border, flat margin, or a single large line is not text evidence.
      const unable = ['Unable to Determine', 'The image could not be assessed confidently. Please inspect the full-size preview before submitting.', 'info'];
      if (Math.min(W,H) < 400) return ['Unable to Determine', 'The image resolution is low. Please inspect the full-size preview or choose a higher-resolution copy.', 'info'];
      if (regions.length < 2) {
        if (globalN && globalL/globalN < 15) return ['Poor Quality', 'The image appears extremely dark. Please consider replacing it.', 'danger'];
        return unable;
      }
      const gradient=median(regions.map(r=>r.gradient));
      const strong=median(regions.map(r=>r.strong));
      const detail=median(regions.map(r=>r.lap));
      const contrast=median(regions.map(r=>r.contrast));
      const brightness=median(regions.map(r=>r.mean));
      // Multiple independent measures must agree before issuing a blur warning.
      if (contrast >= 25 && gradient < 17 && strong < .04 && detail < 12)
        return ['Poor Quality', 'The content appears substantially blurred. Please inspect the full-size preview and consider replacing it.', 'danger'];
      if (contrast >= 25 && gradient < 30 && strong < .23 && detail < 35)
        return ['Possibly Blurry', 'Some content may be difficult to read. Please check the full-size preview.', 'warning'];
      if ((brightness < 45 || brightness > 250) && contrast < 35) return unable;
      if (contrast >= 35 && gradient >= 40 && strong >= .35 && detail >= 45)
        return ['Clear Image', 'Image quality appears sufficient for review.', 'success'];
      return unable;
    }
        function showQuality(node, result) { node.textContent=''; const badge=document.createElement('span'); badge.className='badge badge-soft-'+result[2]+' me-2'; const icon=document.createElement('i'); icon.className='bi '+({success:'bi-check-circle-fill',warning:'bi-exclamation-triangle-fill',danger:'bi-x-circle-fill'}[result[2]] || 'bi-info-circle')+' me-1'; icon.setAttribute('aria-hidden','true'); badge.append(icon, document.createTextNode(result[0])); node.append(badge, document.createTextNode(result[1])); }
    window.assessIncomeProof = async (img, status) => { status.textContent='Checking image quality…'; try { showQuality(status, await assess(img)); } catch (_) { showQuality(status, ['Unable to Determine', 'Please inspect the original carefully.', 'info']); } };
    window.openIncomeProofPreview = enlarge;
    document.querySelectorAll('[data-income-upload]').forEach(root => {
        const input=root.querySelector('input[type=file]'), preview=root.querySelector('[data-preview]'), metadata=root.querySelector('[data-metadata]'), img=root.querySelector('[data-image]'), pdf=root.querySelector('[data-pdf]'), zoom=root.querySelector('[data-enlarge]'), quality=root.querySelector('[data-quality]'), error=root.querySelector('[data-error]');
        let url=null, generation=0;
        root.querySelector('[data-replace]').addEventListener('click',()=>input.click());
        zoom.addEventListener('click',()=>enlarge(url));
        input.addEventListener('change', async () => {
            const version=++generation, file=input.files[0];
            if(url) URL.revokeObjectURL(url); url=null; img.removeAttribute('src'); pdf.removeAttribute('src'); preview.hidden=true; error.textContent=''; input.setCustomValidity('');
            if(!file) return;
            const ext=file.name.toLowerCase().split('.').pop();
            const fail=message=>{ if(version!==generation)return; error.textContent=message; input.setCustomValidity(message); preview.hidden=true; };
            if(!['jpg','jpeg','png','pdf'].includes(ext) || !['image/jpeg','image/png','application/pdf',''].includes(file.type)) return fail('Choose a JPG, PNG, or PDF file.');
            if(file.size>10*1024*1024 || !file.size) return fail('Choose a non-empty file no larger than 10 MB.');
            input.setCustomValidity('Please wait for the file check to finish.');
            preview.hidden=false; metadata.textContent=`${file.name} · ${ext.toUpperCase()} · ${(file.size/1024/1024).toFixed(2)} MB`; quality.textContent='Checking file…';
            try {
                if(ext==='pdf') {
                    const head=new TextDecoder('latin1').decode(await file.slice(0,8).arrayBuffer()), tail=new TextDecoder('latin1').decode(await file.slice(Math.max(0,file.size-2048)).arrayBuffer());
                    if(!head.startsWith('%PDF-') || !tail.includes('%%EOF') || !tail.includes('startxref')) throw new Error('The PDF is incomplete or unreadable.');
                    if(version!==generation)return; url=URL.createObjectURL(file); pdf.src=url; pdf.hidden=false; zoom.hidden=true; quality.textContent='PDF preview available. Image quality is not automatically assessed for PDFs.';
                } else {
                    url=URL.createObjectURL(file); pdf.hidden=true; zoom.hidden=false; img.src=url;
                    try { const result=await assess(img); if(version!==generation)return; showQuality(quality,result); } catch (assessmentError) { if(version!==generation)return; if(!img.naturalWidth || !img.naturalHeight) throw assessmentError; showQuality(quality, ['Unable to Determine', 'Please inspect the full-size preview.', 'info']); }
                }
                if(version===generation) input.setCustomValidity('');
            } catch(e) { fail(e.message || 'This file could not be read.'); }
        });
    });
})();
</script>
@endpush
