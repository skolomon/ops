<style>
	.errModal{
		position:fixed;
		font-family:Arial, Helvetica, sans-serif;
		inset:0;
		background:#0008;
		z-index:99999;
		opacity:0;
		transition:opacity .2s ease-in;
		pointer-events:none;
	}

	.errModBnd .errModal{
		opacity:1;
		pointer-events:auto;
		overflow-y:scroll;
	}

	.errModal .errTitle h2{
		font-size:1.2rem;
		margin:0;
	}

	.errModal>div{
		font-size:1.1rem;
		width:40rem;
		max-width:80%;
		position:relative;
		margin:10% auto;
		padding:.5rem 1rem 1rem;
		border-radius:10px;
		background:#fff;
		border:1px solid #d00a6c;
		box-shadow:inset 0 .25rem 0 #d00a6c;
	}

	.errModal .errTitle{
		display:flex;
		justify-content:space-between;
		align-items:center;
		border-bottom:1px solid #b7c5ca;
		padding:0 1rem .25rem;
		margin:0 -1rem 1rem;
	}

	.errModal .xBtn{
		font-size:2rem;
		font-weight:bold;
		line-height:0;
		width:2rem;
		height:2rem;
		text-align:center;
		background:transparent;
		cursor:pointer;
		margin-right:-.5rem;
		border:none;
		text-decoration:none;
		box-sizing:border-box;
		color:inherit;
	}

	.errModal .xBtn:active,
	.errModal .xBtn:focus-visible{
		outline:0;
		border-radius:4px;
		border:1px solid #006798;
		color:initial;
	}

	.errModal .clsBtn{
		display:block;
		border:1px solid #006798;
		border-radius:4px;
		text-align:center;
		font-size:.875rem;
		line-height:1;
		font-weight:600;
		text-decoration:none;
		cursor:pointer;
		padding:.65rem 1rem;
		color:#fff;
		background:#006798;
		margin-left:auto;
		width:fit-content;
		margin-top:1rem;
	}

	.errModal .clsBtn:hover{
		background:#008acb;
	}
	.errModBnd{
		position:fixed;
		inset:0;
	}
</style>
<div id='errModal' class='errModal' role='dialog' onkeydown='errKeyTab(event)'>
	<div autofocus>
		<div class='errTitle'>
			<h2>{$title}</h2>
			<button id='xBtn' class='xBtn' aria-hidden='true' onclick='errClose()'>&times;</button>
		</div>
		{$message}
		<button id='clsBtn' class='clsBtn' onclick='errClose()'>{translate key="common.close"}</button>
	</div>
</div>
<script>
	clLst=()=>document.body.classList;
	erEl=(id)=>document.getElementById(id);
	document.addEventListener('DOMContentLoaded',()=>clLst().add('errModBnd'));
	errKeyEsc=(e)=>{ if(e.key=='Escape')errClose();};
	document.addEventListener('keydown',errKeyEsc);
	errKeyTab=(e)=>{
		if(e.key=='Tab'){
			if(!e.shiftKey&&e.target.classList.contains('clsBtn')){ erEl('xBtn').focus();e.preventDefault();}
			else if(e.shiftKey&&e.target.classList.contains('xBtn')){ erEl('clsBtn').focus();e.preventDefault();}
		}};
	errClose =()=>{ clLst().remove('errModBnd');document.removeEventListener('keydown',errKeyEsc);setTimeout(()=>erEl('errModal').remove(),500);};
</script>