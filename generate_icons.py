#!/usr/bin/env python3
"""
KES-SMART PWA Icon Generator
Generates PWA icons using the 📝 emoji for offline functionality
"""

import os
from PIL import Image, ImageDraw, ImageFont
import requests
from io import BytesIO

def create_icon_with_emoji(size, emoji="📝", bg_color=(255, 255, 255, 255), output_path="icon.png"):
    """
    Create an icon with emoji - enhanced for exact 📝 appearance
    
    Args:
        size (int): Icon size (width and height)
        emoji (str): Emoji to use as icon
        bg_color (tuple): Background color (R, G, B, A)
        output_path (str): Output file path
    """
    # Create image with white background
    image = Image.new('RGBA', (size, size), bg_color)
    draw = ImageDraw.Draw(image)
    
    # Try to use system emoji font or create custom 📝 icon
    font_size = int(size * 0.8)  # Make emoji 80% of icon size for better visibility
    
    emoji_rendered = False
    
    try:
        # Try different font paths for emoji support
        font_paths = [
            "C:/Windows/Fonts/seguiemj.ttf",  # Windows emoji font
            "C:/Windows/Fonts/segmdl2.ttf",   # Windows symbols
            "C:/Windows/Fonts/NotoColorEmoji.ttf",
            "/System/Library/Fonts/Apple Color Emoji.ttc",  # macOS
            "/usr/share/fonts/truetype/noto/NotoColorEmoji.ttf"  # Linux
        ]
        
        for font_path in font_paths:
            if os.path.exists(font_path):
                try:
                    font = ImageFont.truetype(font_path, font_size)
                    
                    # Test if emoji renders properly
                    test_bbox = draw.textbbox((0, 0), emoji, font=font)
                    test_width = test_bbox[2] - test_bbox[0]
                    
                    if test_width > 10:  # If emoji has reasonable width
                        # Get text bounding box for centering
                        bbox = draw.textbbox((0, 0), emoji, font=font)
                        text_width = bbox[2] - bbox[0]
                        text_height = bbox[3] - bbox[1]
                        
                        # Calculate position to perfectly center the emoji
                        x = (size - text_width) // 2 - bbox[0]  # Adjust for bbox offset
                        y = (size - text_height) // 2 - bbox[1]  # Adjust for bbox offset
                        
                        # Ensure emoji is within bounds
                        x = max(0, min(x, size - text_width))
                        y = max(0, min(y, size - text_height))
                        
                        # Draw emoji on image
                        draw.text((x, y), emoji, font=font, fill=(0, 0, 0, 255))
                        emoji_rendered = True
                        break
                except Exception as e:
                    continue
    except Exception as e:
        pass
    
    # If emoji didn't render properly, create custom 📝 icon
    if not emoji_rendered:
        create_custom_memo_icon(draw, size)
    
    # Add a subtle rounded border for better appearance
    border_width = max(1, size // 128)
    if border_width > 0:
        # Create rounded rectangle border
        corner_radius = size // 16
        draw.rounded_rectangle([border_width, border_width, size-border_width-1, size-border_width-1], 
                             radius=corner_radius, outline=(220, 220, 220, 128), width=border_width)
    
    # Save the image
    image.save(output_path, 'PNG')
    print(f"✅ Created {output_path} ({size}x{size})")

def create_custom_memo_icon(draw, size):
    """
    Create a custom 📝 memo/notepad icon when emoji font isn't available - perfectly centered
    """
    # Colors that match the 📝 emoji
    paper_color = (255, 255, 255, 255)      # White paper
    paper_shadow = (230, 230, 230, 255)     # Light gray shadow
    text_color = (50, 50, 50, 255)          # Dark text
    pencil_wood = (210, 180, 140, 255)      # Tan/wood color
    pencil_metal = (192, 192, 192, 255)     # Silver ferrule
    pencil_eraser = (255, 192, 203, 255)    # Pink eraser
    pencil_tip = (169, 169, 169, 255)       # Gray pencil tip
    
    # Calculate dimensions for perfect centering
    margin = size // 16  # Smaller margin for better centering
    
    # Paper dimensions - centered in the canvas
    paper_width = int(size * 0.65)  # Slightly smaller for better balance
    paper_height = int(size * 0.75)
    paper_x = (size - paper_width) // 2  # Perfect horizontal centering
    paper_y = (size - paper_height) // 2  # Perfect vertical centering
    
    # Draw paper shadow - slightly offset
    shadow_offset = max(2, size // 40)
    draw.rectangle([paper_x + shadow_offset, paper_y + shadow_offset, 
                   paper_x + paper_width + shadow_offset, paper_y + paper_height + shadow_offset], 
                  fill=paper_shadow)
    
    # Draw main paper - perfectly centered
    draw.rectangle([paper_x, paper_y, paper_x + paper_width, paper_y + paper_height], 
                  fill=paper_color, outline=(200, 200, 200, 255), width=max(1, size//100))
    
    # Draw text lines on paper - centered within paper
    num_lines = max(3, min(6, size // 40))  # Scale number of lines with size
    line_spacing = paper_height // (num_lines + 2)  # Even spacing
    line_margin = paper_x + paper_width // 8  # Left margin within paper
    line_end_margin = paper_x + paper_width - paper_width // 8  # Right margin
    line_thickness = max(1, size // 80)
    
    # Start lines after some top margin
    start_y = paper_y + line_spacing
    
    for i in range(num_lines):
        y_pos = start_y + line_spacing * i
        if y_pos < paper_y + paper_height - line_spacing:
            # Vary line lengths for more realistic look, but keep them centered
            line_reduction = (i % 3) * (paper_width // 12)  # Vary every 3rd line
            actual_start = line_margin
            actual_end = line_end_margin - line_reduction
            
            draw.rectangle([actual_start, y_pos, actual_end, y_pos + line_thickness], 
                         fill=text_color)
    
    # Draw pencil - positioned to not overlap with paper but still look natural
    pencil_length = int(size * 0.45)  # Scaled pencil length
    pencil_width = max(3, size // 32)  # Scaled pencil width
    
    # Position pencil in the bottom-right area, but not overlapping paper
    pencil_x = paper_x + paper_width + max(4, size // 40)  # Just outside paper
    pencil_y = paper_y + paper_height - pencil_length + max(8, size // 20)  # Aligned with paper bottom
    
    # Ensure pencil doesn't go outside canvas
    if pencil_x + pencil_width > size - margin:
        pencil_x = size - pencil_width - margin
    if pencil_y < margin:
        pencil_y = margin
    if pencil_y + pencil_length > size - margin:
        pencil_y = size - pencil_length - margin
    
    # Pencil wood body (main part)
    wood_height = int(pencil_length * 0.7)
    draw.rectangle([pencil_x, pencil_y, pencil_x + pencil_width, pencil_y + wood_height], 
                  fill=pencil_wood, outline=(180, 150, 100, 255), width=max(1, size//128))
    
    # Pencil metal ferrule (band)
    ferrule_height = max(2, int(pencil_length * 0.12))
    ferrule_y = pencil_y + wood_height
    draw.rectangle([pencil_x, ferrule_y, pencil_x + pencil_width, ferrule_y + ferrule_height], 
                  fill=pencil_metal, outline=(150, 150, 150, 255), width=max(1, size//128))
    
    # Pencil eraser
    eraser_height = max(2, int(pencil_length * 0.15))
    eraser_y = ferrule_y + ferrule_height
    eraser_end_y = min(eraser_y + eraser_height, pencil_y + pencil_length)
    draw.rectangle([pencil_x, eraser_y, pencil_x + pencil_width, eraser_end_y], 
                  fill=pencil_eraser, outline=(240, 160, 180, 255), width=max(1, size//128))
    
    # Pencil tip (triangle) - at the top
    tip_height = max(2, int(pencil_length * 0.08))
    tip_points = [
        (pencil_x + pencil_width // 2, pencil_y - tip_height),  # Top point
        (pencil_x, pencil_y),  # Bottom left
        (pencil_x + pencil_width, pencil_y)  # Bottom right
    ]
    draw.polygon(tip_points, fill=pencil_tip, outline=(120, 120, 120, 255))
    
    # Add small highlight on paper for 3D effect - perfectly positioned
    highlight_width = max(1, size // 64)
    highlight_height = paper_height // 4
    draw.rectangle([paper_x + 2, paper_y + 2, paper_x + highlight_width + 2, paper_y + highlight_height], 
                  fill=(255, 255, 255, 100))
    
    # Add a small dot/period at the end of one line to simulate writing
    if num_lines >= 2:
        dot_line = num_lines // 2  # Middle line
        dot_y = start_y + line_spacing * dot_line + line_thickness // 2
        dot_x = line_end_margin - paper_width // 6  # Position before line end
        dot_size = max(2, size // 50)
        draw.ellipse([dot_x - dot_size//2, dot_y - dot_size//2, 
                     dot_x + dot_size//2, dot_y + dot_size//2], fill=text_color)

def create_alternative_icon(size, output_path="icon.png"):
    """
    Create an alternative memo-style icon using drawing - matches 📝 theme
    """
    # Create image with white background
    image = Image.new('RGBA', (size, size), (255, 255, 255, 255))
    draw = ImageDraw.Draw(image)
    
    # Use the custom memo icon function
    create_custom_memo_icon(draw, size)
    
    # Add a subtle rounded border
    border_width = max(1, size // 128)
    corner_radius = size // 16
    if border_width > 0:
        draw.rounded_rectangle([border_width, border_width, size-border_width-1, size-border_width-1], 
                             radius=corner_radius, outline=(220, 220, 220, 128), width=border_width)
    
    image.save(output_path, 'PNG')
    print(f"✅ Created memo-style icon {output_path} ({size}x{size})")

def main():
    """Generate all required PWA icons"""
    
    # Create icons directory if it doesn't exist
    icons_dir = "assets/icons"
    os.makedirs(icons_dir, exist_ok=True)
    
    # Define icon sizes for PWA
    icon_sizes = [72, 96, 128, 144, 152, 192, 384, 512]
    
    print("🎨 Generating KES-SMART PWA Icons...")
    print("=" * 40)
    
    # Create icons with emoji
    for size in icon_sizes:
        output_path = os.path.join(icons_dir, f"icon-{size}x{size}.png")
        
        try:
            # Try to create with emoji first
            create_icon_with_emoji(size, "📝", (255, 255, 255, 255), output_path)
        except Exception as e:
            print(f"⚠️  Emoji failed for {size}x{size}, creating alternative...")
            # Fallback to alternative icon
            create_alternative_icon(size, output_path)
    
    # Create favicon.ico
    try:
        # Create a 32x32 favicon
        favicon_path = "favicon.ico"
        create_icon_with_emoji(32, "📝", (255, 255, 255, 255), favicon_path)
        print(f"✅ Created {favicon_path}")
    except:
        create_alternative_icon(32, "favicon.ico")
        print("✅ Created alternative favicon.ico")
    
    # Create Apple touch icon
    try:
        apple_touch_path = os.path.join(icons_dir, "apple-touch-icon.png")
        create_icon_with_emoji(180, "📝", (255, 255, 255, 255), apple_touch_path)
        print(f"✅ Created {apple_touch_path}")
    except:
        create_alternative_icon(180, os.path.join(icons_dir, "apple-touch-icon.png"))
        print("✅ Created alternative apple-touch-icon.png")
    
    print("=" * 40)
    print("🎉 Icon generation complete!")
    print("\n📋 Generated files:")
    print("- PWA icons: 72x72, 96x96, 128x128, 144x144, 152x152, 192x192, 384x384, 512x512")
    print("- favicon.ico (32x32)")
    print("- apple-touch-icon.png (180x180)")
    
    print("\n🔧 Next steps:")
    print("1. The manifest.json is already configured to use these local icons")
    print("2. Add this to your HTML head section:")
    print('   <link rel="icon" href="favicon.ico">')
    print('   <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">')
    print("3. The service worker will cache these icons for offline use")

if __name__ == "__main__":
    # Check if PIL is available
    try:
        from PIL import Image, ImageDraw, ImageFont
    except ImportError:
        print("❌ PIL (Pillow) is required. Install it with:")
        print("   pip install Pillow")
        exit(1)
    
    main()
