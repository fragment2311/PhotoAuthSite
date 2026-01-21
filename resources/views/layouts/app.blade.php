<style>
  header .btn-group{
    display:flex;
    gap:1rem;
    align-items:center;
    flex-wrap:wrap;
  }

  header .btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding: 0.7rem 1.4rem;
    font-size: 1.1rem;
    border-radius: 6px;
    text-decoration:none;
    color:#fff;
    border:2px solid transparent;
    cursor:pointer;
    transition:.2s;
    white-space: nowrap;
  }

  header .btn-primary{
    background: var(--primary-bg);
    border-color: var(--primary-bg);
  }
  header .btn-primary:hover{
    background: var(--link-hover);
    border-color: var(--link-hover);
  }

  header .btn-secondary{
    background: var(--secondary-bg);
    border-color: var(--secondary-bg);
  }
  header .btn-secondary:hover{
    background: var(--muted);
    border-color: var(--muted);
  }

  header .btn-danger{
    background: var(--danger-bg);
    border-color: var(--danger-bg);
  }
  header .btn-danger:hover{
    background: #b91c2a;
    border-color: #b91c2a;
  }
</style>
