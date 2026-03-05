import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { KnowledgeBaseService } from '../../../../chatbot/services/knowledge-base.service';
import { KbDocument, KbArticle } from '../../../../chatbot/models/chat.models';
import { PaginationComponent } from '../../../../../components/shared/pagination/pagination.component';

@Component({
  selector: 'app-kb-documents',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, PaginationComponent],
  templateUrl: './kb-documents.component.html',
})
export class KbDocumentsComponent implements OnInit {
  private kbSvc = inject(KnowledgeBaseService);
  private router = inject(Router);

  documents = signal<KbDocument[]>([]);
  articles = signal<KbArticle[]>([]);
  loading = signal(true);
  uploading = signal(false);
  currentPage = signal(1);
  lastPage = signal(1);
  total = signal(0);
  perPage = signal(15);
  from = signal(0);
  to = signal(0);

  // Filters
  filterEsPlantilla = '';
  filterActivo = '';

  // Upload form
  showUploadForm = false;
  uploadForm = {
    titulo: '',
    descripcion: '',
    es_plantilla: false,
  };
  selectedFile: File | null = null;

  ngOnInit(): void {
    this.load();
    this.loadArticles();
  }

  load(page = 1): void {
    this.loading.set(true);
    const params: any = { page, per_page: 15 };
    if (this.filterEsPlantilla !== '') params['es_plantilla'] = this.filterEsPlantilla;
    if (this.filterActivo !== '')     params['activo'] = this.filterActivo;

    this.kbSvc.getDocuments(params).subscribe({
      next: data => {
        this.documents.set(data.items);
        const pag = data.pagination;
        this.currentPage.set(pag.current_page);
        this.lastPage.set(pag.last_page);
        this.total.set(pag.total);
        this.perPage.set(pag.per_page);
        this.from.set(pag.total === 0 ? 0 : (pag.current_page - 1) * pag.per_page + 1);
        this.to.set(Math.min(pag.current_page * pag.per_page, pag.total));
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  loadArticles(): void {
    this.kbSvc.getArticles({ per_page: 100, activo: true }).subscribe(data => {
      this.articles.set(data.items);
    });
  }

  onFileSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile = input.files?.[0] ?? null;
    if (this.selectedFile && !this.uploadForm.titulo) {
      this.uploadForm.titulo = this.selectedFile.name.replace(/\.[^.]+$/, '');
    }
  }

  upload(): void {
    if (!this.selectedFile || !this.uploadForm.titulo) return;
    this.uploading.set(true);

    const fd = new FormData();
    fd.append('archivo', this.selectedFile);
    fd.append('titulo', this.uploadForm.titulo);
    if (this.uploadForm.descripcion) fd.append('descripcion', this.uploadForm.descripcion);
    fd.append('es_plantilla', this.uploadForm.es_plantilla ? '1' : '0');

    this.kbSvc.uploadDocument(fd).subscribe({
      next: doc => {
        this.documents.update(list => [doc, ...list]);
        this.total.update(t => t + 1);
        this.uploading.set(false);
        this.showUploadForm = false;
        this.resetUploadForm();
      },
      error: () => this.uploading.set(false),
    });
  }

  reprocess(doc: KbDocument): void {
    this.kbSvc.reprocessDocument(doc.id).subscribe(res => {
      this.documents.update(list =>
        list.map(d => d.id === doc.id ? { ...d, procesado: res.procesado } : d)
      );
    });
  }

  toggle(doc: KbDocument): void {
    this.kbSvc.toggleDocument(doc.id).subscribe(res => {
      this.documents.update(list =>
        list.map(d => d.id === doc.id ? { ...d, activo: res.activo } : d)
      );
    });
  }

  delete(doc: KbDocument): void {
    if (!confirm(`¿Eliminar "${doc.titulo}"?`)) return;
    this.kbSvc.deleteDocument(doc.id).subscribe(() => {
      this.documents.update(list => list.filter(d => d.id !== doc.id));
      this.total.update(t => t - 1);
    });
  }

  download(doc: KbDocument): void {
    const url = this.kbSvc.getDownloadUrl(doc.id);
    const token = localStorage.getItem('access_token');
    fetch(url, { headers: { Authorization: `Bearer ${token}` } })
      .then(r => r.blob())
      .then(blob => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = doc.original_filename;
        a.click();
        URL.revokeObjectURL(a.href);
      });
  }

  docArticlesLabel(doc: KbDocument): string {
    const bases = doc.knowledge_bases;
    if (!bases || bases.length === 0) return '—';
    return bases.length === 1
      ? bases[0].titulo
      : `${bases[0].titulo} +${bases.length - 1}`;
  }

  formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
  }

  private resetUploadForm(): void {
    this.uploadForm = { titulo: '', descripcion: '', es_plantilla: false };
    this.selectedFile = null;
  }
}
