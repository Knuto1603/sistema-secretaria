import { Component, ElementRef, ViewChild, AfterViewInit, output, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-signature-pad',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './signature-pad.component.html',
  styleUrl: './signature-pad.component.css'
})
export class AppSignaturePadComponent implements AfterViewInit, OnDestroy {
  @ViewChild('sigCanvas') canvas!: ElementRef<HTMLCanvasElement>;
  private ctx: CanvasRenderingContext2D | null = null;
  private isDrawing = false;
  
  signatureSaved = output<string>();

  ngAfterViewInit() {
    this.initCanvas();
    window.addEventListener('resize', this.onResize);
  }

  ngOnDestroy() {
    window.removeEventListener('resize', this.onResize);
  }

  private onResize = () => {
    this.initCanvas();
  }

  private initCanvas() {
    const canvasEl = this.canvas.nativeElement;
    this.ctx = canvasEl.getContext('2d');
    
    // Ajustar resolución
    canvasEl.width = canvasEl.offsetWidth;
    canvasEl.height = canvasEl.offsetHeight;

    if (this.ctx) {
      this.ctx.strokeStyle = '#1e293b';
      this.ctx.lineWidth = 2;
      this.ctx.lineCap = 'round';
      this.ctx.lineJoin = 'round';
    }
  }

  startDrawing(e: MouseEvent) {
    if (!this.ctx) return;
    this.isDrawing = true;
    this.ctx.beginPath();
    this.ctx.moveTo(e.offsetX, e.offsetY);
  }

  draw(e: MouseEvent) {
    if (!this.isDrawing || !this.ctx) return;
    this.ctx.lineTo(e.offsetX, e.offsetY);
    this.ctx.stroke();
  }

  startDrawingTouch(e: TouchEvent) {
    if (!this.ctx) return;
    e.preventDefault();
    const rect = this.canvas.nativeElement.getBoundingClientRect();
    const touch = e.touches[0];
    this.isDrawing = true;
    this.ctx.beginPath();
    this.ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
  }

  drawTouch(e: TouchEvent) {
    if (!this.isDrawing || !this.ctx) return;
    e.preventDefault();
    const rect = this.canvas.nativeElement.getBoundingClientRect();
    const touch = e.touches[0];
    this.ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
    this.ctx.stroke();
  }

  stopDrawing() {
    if (this.isDrawing) {
      this.isDrawing = false;
      if (this.ctx) {
        this.ctx.closePath();
        this.signatureSaved.emit(this.canvas.nativeElement.toDataURL());
      }
    }
  }

  clear() {
    if (!this.ctx) return;
    this.ctx.clearRect(0, 0, this.canvas.nativeElement.width, this.canvas.nativeElement.height);
    this.signatureSaved.emit('');
  }
}