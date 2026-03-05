import {
  Component,
  OnInit,
  signal,
  inject,
  ViewChild,
  ElementRef,
  AfterViewChecked,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ChatService } from '../../services/chat.service';
import { KnowledgeBaseService } from '../../services/knowledge-base.service';
import { environment } from '@env/environment';
import {
  ChatConversation,
  ChatMessage,
  KbArticleRef,
  KbDocumentRef,
} from '../../models/chat.models';

@Component({
  selector: 'app-chatbot',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './chatbot.component.html',
  styleUrl: './chatbot.component.css',
})
export class ChatbotComponent implements OnInit, AfterViewChecked {
  @ViewChild('messagesEnd') messagesEnd!: ElementRef;

  private chatSvc = inject(ChatService);
  private kbSvc = inject(KnowledgeBaseService);

  conversations = signal<ChatConversation[]>([]);
  activeConversation = signal<{ id: string; titulo: string } | null>(null);
  messages = signal<ChatMessage[]>([]);

  pregunta = '';
  sending = signal(false);
  loadingConvs = signal(true);
  loadingMsgs = signal(false);
  showMobileSidebar = signal(false);

  private shouldScroll = false;

  ngOnInit(): void {
    this.loadConversations();
  }

  ngAfterViewChecked(): void {
    if (this.shouldScroll) {
      this.scrollToBottom();
      this.shouldScroll = false;
    }
  }

  loadConversations(): void {
    this.loadingConvs.set(true);
    this.chatSvc.getConversations().subscribe({
      next: convs => {
        this.conversations.set(convs);
        this.loadingConvs.set(false);
      },
      error: () => this.loadingConvs.set(false),
    });
  }

  selectConversation(conv: ChatConversation): void {
    this.showMobileSidebar.set(false);
    if (this.activeConversation()?.id === conv.id) return;
    this.loadingMsgs.set(true);
    this.chatSvc.getConversation(conv.id).subscribe({
      next: data => {
        this.activeConversation.set(data.conversation);
        this.messages.set(data.messages);
        this.loadingMsgs.set(false);
        this.shouldScroll = true;
      },
      error: () => this.loadingMsgs.set(false),
    });
  }

  newConversation(): void {
    this.showMobileSidebar.set(false);
    this.chatSvc.newConversation().subscribe(conv => {
      this.conversations.update(list => [conv as any, ...list]);
      this.activeConversation.set({ id: conv.id, titulo: conv.titulo });
      this.messages.set([]);
    });
  }

  deleteConversation(id: string, event: Event): void {
    event.stopPropagation();
    if (!confirm('¿Eliminar esta conversación?')) return;
    this.chatSvc.deleteConversation(id).subscribe(() => {
      this.conversations.update(list => list.filter(c => c.id !== id));
      if (this.activeConversation()?.id === id) {
        this.activeConversation.set(null);
        this.messages.set([]);
      }
    });
  }

  sendMessage(): void {
    const text = this.pregunta.trim();
    if (!text || this.sending()) return;

    const convId = this.activeConversation()?.id;
    if (!convId) return;

    // Optimistic: add user message
    const tempUserMsg: ChatMessage = {
      id: 'temp-' + Date.now(),
      role: 'user',
      contenido: text,
      context_articles: [],
      context_documents: [],
      templates_sugeridos: [],
      created_at: new Date().toISOString(),
    };
    this.messages.update(m => [...m, tempUserMsg]);
    this.pregunta = '';
    this.sending.set(true);
    this.shouldScroll = true;

    this.chatSvc.sendMessage(convId, text).subscribe({
      next: resp => {
        // Combinar message con las fuentes (context_articles, etc. vienen en sources, no en message)
        const assistantMsg: ChatMessage = {
          ...resp.message,
          context_articles:   resp.sources.articles   ?? [],
          context_documents:  resp.sources.documents  ?? [],
          templates_sugeridos: resp.sources.templates ?? [],
        };
        this.messages.update(m => [...m, assistantMsg]);
        // Actualizar título de la conversación si cambió
        this.conversations.update(list =>
          list.map(c => c.id === convId ? { ...c, titulo: this.activeConversation()!.titulo } : c)
        );
        this.sending.set(false);
        this.shouldScroll = true;
      },
      error: err => {
        this.messages.update(m => m.filter(x => x.id !== tempUserMsg.id));
        this.pregunta = text;
        this.sending.set(false);
        alert(err.error?.message || 'Error al enviar el mensaje. Intenta de nuevo.');
      },
    });
  }

  onKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.sendMessage();
    }
  }

  downloadDocument(doc: KbDocumentRef, event: Event): void {
    event.preventDefault();
    const url = this.kbSvc.getDownloadUrl(doc.id);
    // Trigger download using token-based request
    const token = localStorage.getItem('access_token');
    fetch(url, { headers: { Authorization: `Bearer ${token}` } })
      .then(r => r.blob())
      .then(blob => {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = doc.titulo;
        a.click();
        URL.revokeObjectURL(a.href);
      });
  }

  getTypeBadgeClass(tipo: string): string {
    const map: Record<string, string> = {
      proceso:    'bg-blue-100 text-blue-700',
      faq:        'bg-green-100 text-green-700',
      norma:      'bg-purple-100 text-purple-700',
      requisito:  'bg-orange-100 text-orange-700',
      resolucion: 'bg-red-100 text-red-700',
    };
    return map[tipo] ?? 'bg-gray-100 text-gray-700';
  }

  formatRelativeTime(dateStr: string): string {
    if (!dateStr) return '';
    // Laravel puede devolver "2026-02-24 10:30:00" (espacio en lugar de T)
    const normalized = dateStr.includes('T') ? dateStr : dateStr.replace(' ', 'T');
    const date = new Date(normalized);
    if (isNaN(date.getTime())) return '';
    const diff = Date.now() - date.getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Ahora';
    if (mins < 60) return `Hace ${mins} min`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `Hace ${hrs} h`;
    const days = Math.floor(hrs / 24);
    return `Hace ${days} d`;
  }

  // Split contenido on «quotes» to highlight citations
  parseMessageParts(contenido: string): { text: string; isCitation: boolean }[] {
    const parts: { text: string; isCitation: boolean }[] = [];
    const regex = /«([^»]+)»/g;
    let last = 0;
    let match;
    while ((match = regex.exec(contenido)) !== null) {
      if (match.index > last) {
        parts.push({ text: contenido.slice(last, match.index), isCitation: false });
      }
      parts.push({ text: '«' + match[1] + '»', isCitation: true });
      last = match.index + match[0].length;
    }
    if (last < contenido.length) {
      parts.push({ text: contenido.slice(last), isCitation: false });
    }
    return parts;
  }

  private scrollToBottom(): void {
    try {
      this.messagesEnd?.nativeElement.scrollIntoView({ behavior: 'smooth' });
    } catch {}
  }
}
